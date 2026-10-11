<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_locations(): void
    {
        $user = $this->administrator();

        $this->actingAs($user)->get(route('locations.index'))
            ->assertOk()
            ->assertSee('Location Directory');

        $this->actingAs($user)->post(route('locations.store'), [
            'location_code' => 'mnl-hub-01',
            'location_name' => 'Manila Central Hub',
            'location_long' => '120.98421950',
            'location_lat' => '14.59951240',
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', [
            'location_code' => 'MNL-HUB-01',
            'location_name' => 'Manila Central Hub',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'is_deleted' => null,
        ]);
    }

    public function test_location_code_must_be_unique_and_coordinates_must_be_valid(): void
    {
        Location::create([
            'location_code' => 'MNL-HUB-02',
            'location_name' => 'Existing Location',
            'location_long' => 120,
            'location_lat' => 14,
        ]);

        $response = $this->actingAs($this->administrator())->post(route('locations.store'), [
            'location_code' => 'MNL-HUB-02',
            'location_name' => 'Invalid Location',
            'location_long' => '181',
            'location_lat' => '-91',
        ]);

        $response->assertSessionHasErrors(['location_code', 'location_long', 'location_lat']);
    }

    public function test_administrator_can_update_and_delete_a_location(): void
    {
        $user = $this->administrator();
        $location = Location::create([
            'location_code' => 'CEB-HUB-01',
            'location_name' => 'Cebu Hub',
            'location_long' => '123.88540000',
            'location_lat' => '10.31570000',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('locations.update', $location), [
            'location_code' => 'CEB-HUB-02',
            'location_name' => 'Cebu South Hub',
            'location_long' => '123.88550000',
            'location_lat' => '10.31580000',
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'location_code' => 'CEB-HUB-02',
            'location_name' => 'Cebu South Hub',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->delete(route('locations.destroy', $location))->assertRedirect();
        $this->assertNotNull($location->fresh()->is_deleted);
        $this->actingAs($user)->get(route('locations.index'))
            ->assertOk()
            ->assertDontSee('Cebu South Hub');
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
