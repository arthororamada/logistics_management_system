<?php

namespace Tests\Feature;

use App\Models\Pickup;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PickupManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutVite();

        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        User::create(['name' => 'Administrator', 'username' => 'admin', 'password' => 'test-password']);
        (require database_path('migrations/2026_08_31_010000_create_roles_and_permissions_tables.php'))->up();
        (require database_path('migrations/2026_09_06_240000_create_personnel_table.php'))->up();
        (require database_path('migrations/2026_10_10_010000_create_pickups_table.php'))->up();
        (require database_path('migrations/2026_10_10_040000_add_driver_id_to_pickups_table.php'))->up();
        $this->personnel('DRIVER-001');
        (require database_path('migrations/2026_10_10_020000_create_pickup_details_table.php'))->up();
        // The MariaDB compatibility migration uses vendor-specific SQL; use the resulting schema here.
        Schema::table('pickup_details', fn (Blueprint $table) => $table->renameColumn('pickup_dm', 'dm'));
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('package_id', 100);
            $table->string('package_status', 100);
            $table->string('buyer_name');
            $table->string('buyer_full_address', 100);
            $table->boolean('is_deleted')->default(false);
        });
    }

    public function test_administrator_can_open_pickup_from_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('pickup.index'))
            ->assertOk()->assertSee('Add Waybill Number/s')->assertSee('Create Document Manifest')
            ->assertDontSee('Save Pickup');
    }

    public function test_bulk_entry_is_validated_accumulated_and_saved_only_on_create(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->package('UNRELATED');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->post(route('pickup.waybills.add'), [
            'waybill_numbers' => "001234\r\nWB-002, WB-003;WB-004\tWB-005\n001234",
        ])->assertRedirect(route('pickup.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('pickup.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005']);
        $this->assertSame(0, Pickup::count());
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'package_status' => 'for_pickup']);
        $this->post(route('pickup.waybills.add'), ['waybill_numbers' => '001234'])->assertSessionHasNoErrors();
        $this->get(route('pickup.index'))->assertOk()->assertSee('001234')->assertSee('Remove');
        $response = $this->post(route('pickup.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')]);
        $pickup = Pickup::firstOrFail();
        $response->assertRedirect(route('pickup.show', $pickup))->assertSessionHasNoErrors()
            ->assertSessionMissing('pickup.waybill_numbers');
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $pickup->waybill_numbers);
        $this->assertSame($this->administrator()->id, $pickup->created_by);
        $this->assertSame(Personnel::where('employee_id', 'DRIVER-001')->value('id'), $pickup->driver_id);
        $this->assertDatabaseCount('pickup_details', 0);
        foreach ($pickup->waybill_numbers as $number) {
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'for_pickup_dispatch']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'for_pickup']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'for_pickup']);
        $this->get(route('pickup.show', $pickup))->assertOk()->assertSee('Dispatch Document Manifest')->assertSee('Test Buyer')->assertSee('DRIVER-001');
        $this->post(route('pickup.dispatch', $pickup))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pickup_details', 5);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 0, 'package_status' => 'pickup_dispatched']);
    }

    public function test_unknown_and_deleted_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['VALID']])
            ->from(route('pickup.index'))->post(route('pickup.waybills.add'), [
                'waybill_numbers' => 'VALID UNKNOWN DELETED',
            ])->assertRedirect(route('pickup.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('pickup.waybill_numbers', ['VALID']);
        $this->get(route('pickup.index'))->assertSee('Waybill UNKNOWN')->assertSee('Waybill DELETED');
        $this->assertDatabaseCount('pickups', 0);
    }

    public function test_invalid_bulk_entries_do_not_add_numbers(): void
    {
        foreach (['', " , ; \n", str_repeat('A', 101), implode("\n", range(1, 1001)), ['WB-001']] as $input) {
            $this->actingAs($this->administrator())->from(route('pickup.index'))
                ->post(route('pickup.waybills.add'), ['waybill_numbers' => $input])
                ->assertRedirect(route('pickup.index'))->assertSessionHasErrors('waybill_numbers')
                ->assertSessionMissing('pickup.waybill_numbers');
        }
        $this->assertDatabaseCount('pickups', 0);
    }

    public function test_dispatched_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        foreach (['VALID', 'NEW', 'DISPATCHED-001', 'DISPATCHED-002'] as $number) {
            $this->package($number);
        }
        $manifest = $this->manifest(['DISPATCHED-001', 'DISPATCHED-002']);
        foreach ($manifest->waybill_numbers as $number) {
            DB::table('pickup_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => 'dispatched']);
        }
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['VALID']])
            ->from(route('pickup.index'))->post(route('pickup.waybills.add'), [
                'waybill_numbers' => 'NEW DISPATCHED-001 DISPATCHED-002',
            ])->assertRedirect(route('pickup.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('pickup.waybill_numbers', ['VALID']);
        $this->get(route('pickup.index'))->assertSee('Waybill DISPATCHED-001 has already been dispatched')
            ->assertSee('Waybill DISPATCHED-002 has already been dispatched');
        $this->assertDatabaseCount('pickups', 1);
    }

    public function test_other_pickup_detail_statuses_do_not_block_adding_waybills(): void
    {
        $numbers = ['FAILED', 'CANCELLED', 'SUCCESS'];
        $manifest = $this->manifest($numbers);
        foreach (['FAILED' => 'failed', 'CANCELLED' => 'cancelled', 'SUCCESS' => 'success'] as $number => $status) {
            $this->package($number);
            DB::table('pickup_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => $status]);
        }
        $this->actingAs($this->administrator())->post(route('pickup.waybills.add'), ['waybill_numbers' => implode(',', $numbers)])
            ->assertRedirect(route('pickup.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('pickup.waybill_numbers', $numbers);
    }

    public function test_dispatched_waybills_are_rechecked_before_creation_and_dispatch(): void
    {
        $this->package('WB-001');
        $dispatched = $this->manifest(['WB-001']);
        DB::table('pickup_details')->insert(['dm' => $dispatched->id, 'waybill_number' => 'WB-001', 'status' => 'dispatched']);
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['WB-001']])
            ->from(route('pickup.index'))->post(route('pickup.store'), [
                'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'),
            ])->assertSessionHasErrors('waybill_numbers')->assertSessionHas('pickup.waybill_numbers', ['WB-001']);
        $this->assertDatabaseCount('pickups', 1);
        $pending = $this->manifest(['WB-001']);
        $this->from(route('pickup.show', $pending))->post(route('pickup.dispatch', $pending))
            ->assertRedirect(route('pickup.show', $pending))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('pickup_details', 1);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'WB-001', 'package_status' => 'for_pickup']);
    }

    public function test_waybills_can_be_removed_from_the_draft(): void
    {
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('pickup.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('pickup.index'))->assertSessionHas('pickup.waybill_numbers', ['WB-002']);
    }

    public function test_create_requires_a_valid_draft_and_revalidates_packages(): void
    {
        $driverId = Personnel::where('employee_id', 'DRIVER-001')->value('id');
        $this->actingAs($this->administrator())->from(route('pickup.index'))
            ->post(route('pickup.store'), ['waybill_numbers' => 'BYPASS', 'driver_id' => $driverId])->assertSessionHasErrors('waybill_numbers');
        $this->package('DELETED', true);
        $this->withSession(['pickup.waybill_numbers' => ['DELETED']])->post(route('pickup.store'), ['driver_id' => $driverId])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('pickup.waybill_numbers', ['DELETED']);
        $this->assertDatabaseCount('pickups', 0);
    }

    public function test_driver_dropdown_only_lists_active_drivers(): void
    {
        $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $this->personnel('HELPER-001', 'Helper');
        $this->personnel('LOWERCASE-DRIVER', 'driver');
        $this->actingAs($this->administrator())->get(route('pickup.index'))
            ->assertOk()->assertSee('name="driver_id"', false)->assertSee('DRIVER-001')
            ->assertSee('LOWERCASE-DRIVER')->assertDontSee('INACTIVE-DRIVER')->assertDontSee('HELPER-001');
    }

    public function test_creation_requires_an_existing_active_driver_and_preserves_draft_on_failure(): void
    {
        $inactive = $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $helper = $this->personnel('HELPER-001', 'Helper');
        $this->package('VALID');
        foreach ([null, 999999, $inactive->id, $helper->id, 'invalid'] as $driverId) {
            $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['VALID']])
                ->from(route('pickup.index'))->post(route('pickup.store'), ['driver_id' => $driverId])
                ->assertRedirect(route('pickup.index'))->assertSessionHasErrors('driver_id')
                ->assertSessionHas('pickup.waybill_numbers', ['VALID']);
        }
        $this->assertDatabaseCount('pickups', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_pickup']);
    }

    public function test_driver_search_queries_current_active_drivers_by_name_and_employee_id(): void
    {
        $driver = $this->personnel('SEARCH-002', ' driver ');
        $driver->update(['first_name' => 'Maria', 'last_name' => 'Santos', 'middle_name' => 'Cruz']);
        $this->personnel('SEARCH-INACTIVE', 'Driver', false);
        $this->personnel('SEARCH-HELPER', 'Helper');
        $this->actingAs($this->administrator());
        foreach (['SEARCH-002', 'Maria', 'Santos', 'Cruz', 'Maria Santos'] as $search) {
            $this->getJson(route('pickup.drivers.search', ['search' => $search]))
                ->assertOk()->assertJsonCount(1, 'drivers')->assertJsonPath('drivers.0.id', $driver->id)
                ->assertJsonPath('drivers.0.label', 'Santos, Maria Cruz (SEARCH-002)');
        }
        $this->getJson(route('pickup.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(1, 'drivers');
        $driver->update(['is_active' => false]);
        $this->getJson(route('pickup.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(0, 'drivers');
        $this->getJson(route('pickup.drivers.search', ['search' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_driver_search_limits_results_and_preserves_selected_driver_on_validation_errors(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->personnel('SEARCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
        $driver = $this->personnel('LAST-DRIVER');
        $driver->update(['last_name' => 'Zzz']);
        $this->actingAs($this->administrator())->getJson(route('pickup.drivers.search'))
            ->assertOk()->assertJsonCount(20, 'drivers');
        $this->withSession(['_old_input' => ['driver_id' => $driver->id]])->get(route('pickup.index'))
            ->assertOk()->assertSee('LAST-DRIVER')->assertSee('Search drivers');
    }

    public function test_driver_search_requires_authentication_and_pickup_create_permission(): void
    {
        $this->getJson(route('pickup.drivers.search'))->assertUnauthorized();
        $user = User::create(['name' => 'Viewer', 'username' => 'driver-viewer', 'password' => 'test-password']);
        $this->actingAs($user)->getJson(route('pickup.drivers.search'))->assertForbidden();
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'pickup.view')->value('id')]);
        $this->getJson(route('pickup.drivers.search'))->assertForbidden();
    }

    public function test_dispatch_creates_one_detail_per_waybill_and_repeated_dispatch_is_safe(): void
    {
        $this->package('001234');
        $this->package('WB-002');
        $this->package('UNRELATED');
        $this->package('001234', true);
        $pickup = $this->manifest(['001234', 'WB-002']);
        DB::table('packages')->whereIn('waybill_number', $pickup->waybill_numbers)->where('is_deleted', 0)
            ->update(['package_status' => 'for_pickup_dispatch']);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($this->administrator())->post(route('pickup.dispatch', $pickup))
                ->assertRedirect(route('pickup.show', $pickup))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('pickup_details', 2);
        foreach ($pickup->waybill_numbers as $number) {
            $this->assertDatabaseHas('pickup_details', ['dm' => $pickup->id, 'waybill_number' => $number, 'status' => 'dispatched']);
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'pickup_dispatched']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'for_pickup']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'for_pickup']);
        $this->assertNotNull(DB::table('pickup_details')->first()->created_at);
        $this->get(route('pickup.show', $pickup))->assertOk()->assertSee('dispatched')->assertDontSee('Dispatch Document Manifest');
    }

    public function test_dispatch_rejects_invalid_packages_without_creating_any_details(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $pickup = $this->manifest(['VALID', 'DELETED']);
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_pickup_dispatch']);
        $this->actingAs($this->administrator())->from(route('pickup.show', $pickup))
            ->post(route('pickup.dispatch', $pickup))->assertRedirect(route('pickup.show', $pickup))
            ->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('pickup_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_pickup_dispatch']);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'DELETED', 'package_status' => 'for_pickup']);
    }

    public function test_pickup_requires_authentication_and_permissions(): void
    {
        $pickup = $this->manifest(['WB-001']);
        $this->get(route('pickup.index'))->assertRedirect(route('login'));
        $this->get(route('pickup.show', $pickup))->assertRedirect(route('login'));
        foreach (['pickup.waybills.add', 'pickup.store', 'pickup.dispatch'] as $route) {
            $this->post(route($route, $route === 'pickup.dispatch' ? $pickup : []))->assertRedirect(route('login'));
        }
        $user = User::create(['name' => 'Pickup Test', 'username' => 'pickup-test', 'password' => 'test-password']);
        $this->actingAs($user)->get(route('pickup.index'))->assertForbidden();
        $this->get(route('pickup.show', $pickup))->assertForbidden();
        foreach (['pickup.waybills.add', 'pickup.store', 'pickup.dispatch'] as $route) {
            $this->post(route($route, $route === 'pickup.dispatch' ? $pickup : []))->assertForbidden();
        }
        $this->delete(route('pickup.waybills.remove'))->assertForbidden();
    }

    private function personnel(string $employeeId, string $position = 'Driver', bool $active = true): Personnel
    {
        return Personnel::create([
            'employee_id' => $employeeId, 'first_name' => 'Test', 'last_name' => 'Driver',
            'address' => 'Test Address', 'contact_number' => '09123456789',
            'position' => $position, 'personnel_type' => 'INHOUSE', 'warehouse_code' => 'WH-01', 'is_active' => $active,
        ]);
    }

    private function package(string $number, bool $deleted = false): void
    {
        DB::table('packages')->insert([
            'waybill_number' => $number, 'package_id' => 'PKG-'.$number,
            'package_status' => 'for_pickup', 'buyer_name' => 'Test Buyer',
            'buyer_full_address' => 'Test Address', 'is_deleted' => $deleted,
        ]);
    }

    private function manifest(array $numbers): Pickup
    {
        return Pickup::create(['waybill_numbers' => $numbers, 'created_by' => $this->administrator()->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_all_active_packages_must_be_for_pickup_before_adding(): void
    {
        $this->package('VALID');
        $this->package('MIXED');
        $this->package('MIXED');
        $packageId = DB::table('packages')->where('waybill_number', 'MIXED')->value('id');
        DB::table('packages')->where('id', $packageId)->update(['package_status' => 'pending']);
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['VALID']])
            ->from(route('pickup.index'))->post(route('pickup.waybills.add'), ['waybill_numbers' => 'MIXED'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('pickup.waybill_numbers', ['VALID']);
        $this->get(route('pickup.index'))->assertSee('Waybill MIXED must have package status for_pickup');
        $this->package('VALID', true);
        DB::table('packages')->where('waybill_number', 'VALID')->where('is_deleted', 1)->update(['package_status' => 'pending']);
        $this->post(route('pickup.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
    }

    public function test_creation_rechecks_for_pickup_status_and_keeps_the_draft_on_failure(): void
    {
        $this->package('CHANGED');
        $this->actingAs($this->administrator())->post(route('pickup.waybills.add'), ['waybill_numbers' => 'CHANGED'])
            ->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'accepted_to_warehouse']);
        $this->post(route('pickup.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('pickup.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('pickups', 0);
        $this->assertDatabaseCount('pickup_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'CHANGED', 'package_status' => 'accepted_to_warehouse']);
    }
}
