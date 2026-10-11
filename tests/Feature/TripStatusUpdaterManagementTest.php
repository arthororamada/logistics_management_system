<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TripStatusUpdaterManagementTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function trip(string $status = 'Open'): Trip
    {
        $trip = Trip::create(['trip_id' => 'TRIP-'.Str::ulid(), 'creation_key' => (string) Str::uuid(), 'current_status' => $status, 'version' => 0, 'van_number' => 'TEST-VAN', 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $trip->history()->create(['status' => $status, 'created_by' => $this->admin->id]);

        return $trip;
    }

    private function change(Trip $trip, array $overrides = [])
    {
        return $this->putJson(route('trip-status-updaters.update'), array_replace(['trip_id' => $trip->trip_id, 'version' => $trip->fresh()->version, 'new_status' => 'Cancelled', 'reason' => 'Customer request'], $overrides));
    }

    public function test_page_starts_empty_and_search_returns_only_an_exact_match(): void
    {
        $trip = $this->trip();
        $this->get(route('trip-status-updaters.index', ['trip_id' => $trip->trip_id]))->assertOk()->assertSee('No trip selected.')->assertDontSee($trip->trip_id);
        $this->getJson(route('trip-status-updaters.search', ['trip_id' => ' '.$trip->trip_id.' ']))->assertOk()->assertJsonPath('trip.trip_id', $trip->trip_id)->assertJsonPath('trip.van_number', 'TEST-VAN')->assertJsonPath('trip.current_status', 'Open')->assertJsonPath('trip.version', 0);
        $this->getJson(route('trip-status-updaters.search', ['trip_id' => substr($trip->trip_id, 0, -1)]))->assertNotFound()->assertJsonPath('message', 'Trip does not exist.');
        $this->getJson(route('trip-status-updaters.search'))->assertUnprocessable()->assertJsonValidationErrors('trip_id');
    }

    public function test_updates_use_database_status_and_preserve_repeated_audit_reasons(): void
    {
        $trip = $this->trip('Completed');
        $trip->update(['date_completed' => now(), 'total_cost' => 123]);
        $this->change($trip, ['new_status' => 'Open', 'current_status' => 'Fabricated', 'reason' => ' Reopen delivery '])->assertOk()->assertJsonPath('trip.current_status', 'Open')->assertJsonPath('trip.version', 1);
        $this->assertNull($trip->fresh()->date_completed);
        $this->assertEquals(123, $trip->fresh()->total_cost);
        $this->change($trip, ['reason' => 'First cancellation'])->assertOk();
        $this->change($trip, ['new_status' => 'Open', 'reason' => 'Retry'])->assertOk();
        $this->change($trip, ['reason' => 'Second cancellation'])->assertOk();
        $this->assertEquals(5, $trip->history()->count());
        $this->assertEquals(['First cancellation', 'Second cancellation'], $trip->history()->where('status', 'Cancelled')->pluck('reason')->all());
        $this->assertDatabaseHas('trip_status', ['trip_id' => $trip->trip_id, 'status' => 'Open', 'reason' => 'Reopen delivery', 'created_by' => $this->admin->id]);
    }

    public function test_invalid_inputs_and_stale_versions_do_not_change_the_trip(): void
    {
        $trip = $this->trip();
        foreach ([['reason' => '  '], ['new_status' => 'Completed'], ['version' => -1], ['trip_id' => null]] as $invalid) {
            $this->change($trip, $invalid)->assertUnprocessable();
        }
        $this->change($trip, ['trip_id' => 'missing-trip'])->assertNotFound();
        $this->change($trip, ['new_status' => 'Open'])->assertUnprocessable();
        $this->assertEquals(1, $trip->history()->count());
        $this->change($trip)->assertOk();
        $this->change($trip, ['version' => 0, 'new_status' => 'Open'])->assertConflict();
        $this->assertEquals('Cancelled', $trip->fresh()->current_status);
        $this->assertEquals(2, $trip->history()->count());
    }

    public function test_permissions_and_existing_trip_ownership_are_enforced(): void
    {
        $trip = $this->trip();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('trip-status-updaters.index'))->assertForbidden();
        $this->change($trip)->assertForbidden();
        $role = Role::create(['name' => 'Status viewer', 'slug' => 'status-viewer-'.Str::ulid()]);
        $role->permissions()->attach(Permission::where('slug', 'trip-status-updaters.view')->value('id'));
        $user->roles()->attach($role);
        $this->get(route('trip-status-updaters.index'))->assertOk()->assertDontSee('data-status-update', false);
        $this->getJson(route('trip-status-updaters.search', ['trip_id' => $trip->trip_id]))->assertNotFound();
        $trip->update(['created_by' => $user->id]);
        $this->getJson(route('trip-status-updaters.search', ['trip_id' => $trip->trip_id]))->assertOk();
        $this->change($trip)->assertForbidden();
        $role->permissions()->attach(Permission::where('slug', 'trip-status-updaters.update')->value('id'));
        $this->change($trip)->assertOk();
    }

    public function test_database_failures_roll_back_status_changes(): void
    {
        $trip = $this->trip();
        $this->mock(TripService::class)->shouldReceive('updateStatus')->once()->andReturnUsing(function (Trip $record) {
            $record->update(['current_status' => 'Cancelled']);
            throw new QueryException('mysql', 'test failure', [], new \PDOException('Simulated database failure'));
        });
        $this->change($trip)->assertStatus(500)->assertJsonPath('message', 'Unable to update trip status. No changes were saved. Please try again.');
        $this->assertEquals('Open', $trip->fresh()->current_status);
        $this->assertEquals(1, $trip->history()->count());
    }
}
