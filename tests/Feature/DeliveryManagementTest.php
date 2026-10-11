<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
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
        (require database_path('migrations/2026_10_11_040000_create_deliveries_table.php'))->up();
        (require database_path('migrations/2026_10_11_060000_add_delivery_permissions.php'))->up();
        $this->personnel('DRIVER-001');
        (require database_path('migrations/2026_10_11_050000_create_delivery_details_table.php'))->up();
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

    public function test_administrator_can_open_delivery_from_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('delivery.index'))
            ->assertOk()->assertSee('Add Waybill Number/s')->assertSee('Create Document Manifest')
            ->assertDontSee('Save Delivery');
    }

    public function test_bulk_entry_is_validated_accumulated_and_saved_only_on_create(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->package('UNRELATED');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->post(route('delivery.waybills.add'), [
            'waybill_numbers' => "001234\r\nWB-002, WB-003;WB-004\tWB-005\n001234",
        ])->assertRedirect(route('delivery.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('delivery.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005']);
        $this->assertSame(0, Delivery::count());
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'package_status' => 'accepted_to_warehouse']);
        $this->post(route('delivery.waybills.add'), ['waybill_numbers' => '001234'])->assertSessionHasNoErrors();
        $this->get(route('delivery.index'))->assertOk()->assertSee('001234')->assertSee('Remove');
        $response = $this->post(route('delivery.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')]);
        $delivery = Delivery::firstOrFail();
        $response->assertRedirect(route('delivery.show', $delivery))->assertSessionHasNoErrors()
            ->assertSessionMissing('delivery.waybill_numbers');
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $delivery->waybill_numbers);
        $this->assertSame($this->administrator()->id, $delivery->created_by);
        $this->assertSame(Personnel::where('employee_id', 'DRIVER-001')->value('id'), $delivery->driver_id);
        $this->assertDatabaseCount('delivery_details', 0);
        foreach ($delivery->waybill_numbers as $number) {
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'for_delivery_dispatch']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'accepted_to_warehouse']);
        $this->get(route('delivery.show', $delivery))->assertOk()->assertSee('Dispatch Document Manifest')->assertSee('Test Buyer')->assertSee('DRIVER-001');
        $this->post(route('delivery.dispatch', $delivery))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('delivery_details', 5);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 0, 'package_status' => 'delivery_dispatched']);
    }

    public function test_unknown_and_deleted_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['VALID']])
            ->from(route('delivery.index'))->post(route('delivery.waybills.add'), [
                'waybill_numbers' => 'VALID UNKNOWN DELETED',
            ])->assertRedirect(route('delivery.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('delivery.waybill_numbers', ['VALID']);
        $this->get(route('delivery.index'))->assertSee('Waybill UNKNOWN')->assertSee('Waybill DELETED');
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_invalid_bulk_entries_do_not_add_numbers(): void
    {
        foreach (['', " , ; \n", str_repeat('A', 101), implode("\n", range(1, 1001)), ['WB-001']] as $input) {
            $this->actingAs($this->administrator())->from(route('delivery.index'))
                ->post(route('delivery.waybills.add'), ['waybill_numbers' => $input])
                ->assertRedirect(route('delivery.index'))->assertSessionHasErrors('waybill_numbers')
                ->assertSessionMissing('delivery.waybill_numbers');
        }
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_dispatched_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        foreach (['VALID', 'NEW', 'DISPATCHED-001', 'DISPATCHED-002'] as $number) {
            $this->package($number);
        }
        $manifest = $this->manifest(['DISPATCHED-001', 'DISPATCHED-002']);
        foreach ($manifest->waybill_numbers as $number) {
            DB::table('delivery_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => 'dispatched']);
        }
        $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['VALID']])
            ->from(route('delivery.index'))->post(route('delivery.waybills.add'), [
                'waybill_numbers' => 'NEW DISPATCHED-001 DISPATCHED-002',
            ])->assertRedirect(route('delivery.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('delivery.waybill_numbers', ['VALID']);
        $this->get(route('delivery.index'))->assertSee('Waybill DISPATCHED-001 has already been dispatched')
            ->assertSee('Waybill DISPATCHED-002 has already been dispatched');
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_other_delivery_detail_statuses_do_not_block_adding_waybills(): void
    {
        $numbers = ['FAILED', 'CANCELLED', 'SUCCESS'];
        $manifest = $this->manifest($numbers);
        foreach (['FAILED' => 'failed', 'CANCELLED' => 'cancelled', 'SUCCESS' => 'success'] as $number => $status) {
            $this->package($number);
            DB::table('delivery_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => $status]);
        }
        $this->actingAs($this->administrator())->post(route('delivery.waybills.add'), ['waybill_numbers' => implode(',', $numbers)])
            ->assertRedirect(route('delivery.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('delivery.waybill_numbers', $numbers);
    }

    public function test_dispatched_waybills_are_rechecked_before_creation_and_dispatch(): void
    {
        $this->package('WB-001');
        $dispatched = $this->manifest(['WB-001']);
        DB::table('delivery_details')->insert(['dm' => $dispatched->id, 'waybill_number' => 'WB-001', 'status' => 'dispatched']);
        $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['WB-001']])
            ->from(route('delivery.index'))->post(route('delivery.store'), [
                'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'),
            ])->assertSessionHasErrors('waybill_numbers')->assertSessionHas('delivery.waybill_numbers', ['WB-001']);
        $this->assertDatabaseCount('deliveries', 1);
        $pending = $this->manifest(['WB-001']);
        $this->from(route('delivery.show', $pending))->post(route('delivery.dispatch', $pending))
            ->assertRedirect(route('delivery.show', $pending))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('delivery_details', 1);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'WB-001', 'package_status' => 'accepted_to_warehouse']);
    }

    public function test_waybills_can_be_removed_from_the_draft(): void
    {
        $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('delivery.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('delivery.index'))->assertSessionHas('delivery.waybill_numbers', ['WB-002']);
    }

    public function test_create_requires_a_valid_draft_and_revalidates_packages(): void
    {
        $driverId = Personnel::where('employee_id', 'DRIVER-001')->value('id');
        $this->actingAs($this->administrator())->from(route('delivery.index'))
            ->post(route('delivery.store'), ['waybill_numbers' => 'BYPASS', 'driver_id' => $driverId])->assertSessionHasErrors('waybill_numbers');
        $this->package('DELETED', true);
        $this->withSession(['delivery.waybill_numbers' => ['DELETED']])->post(route('delivery.store'), ['driver_id' => $driverId])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('delivery.waybill_numbers', ['DELETED']);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_driver_dropdown_only_lists_active_drivers(): void
    {
        $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $this->personnel('HELPER-001', 'Helper');
        $this->personnel('LOWERCASE-DRIVER', 'driver');
        $this->actingAs($this->administrator())->get(route('delivery.index'))
            ->assertOk()->assertSee('name="driver_id"', false)->assertSee('DRIVER-001')
            ->assertSee('LOWERCASE-DRIVER')->assertDontSee('INACTIVE-DRIVER')->assertDontSee('HELPER-001');
    }

    public function test_creation_requires_an_existing_active_driver_and_preserves_draft_on_failure(): void
    {
        $inactive = $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $helper = $this->personnel('HELPER-001', 'Helper');
        $this->package('VALID');
        foreach ([null, 999999, $inactive->id, $helper->id, 'invalid'] as $driverId) {
            $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['VALID']])
                ->from(route('delivery.index'))->post(route('delivery.store'), ['driver_id' => $driverId])
                ->assertRedirect(route('delivery.index'))->assertSessionHasErrors('driver_id')
                ->assertSessionHas('delivery.waybill_numbers', ['VALID']);
        }
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'accepted_to_warehouse']);
    }

    public function test_driver_search_queries_current_active_drivers_by_name_and_employee_id(): void
    {
        $driver = $this->personnel('SEARCH-002', ' driver ');
        $driver->update(['first_name' => 'Maria', 'last_name' => 'Santos', 'middle_name' => 'Cruz']);
        $this->personnel('SEARCH-INACTIVE', 'Driver', false);
        $this->personnel('SEARCH-HELPER', 'Helper');
        $this->actingAs($this->administrator());
        foreach (['SEARCH-002', 'Maria', 'Santos', 'Cruz', 'Maria Santos'] as $search) {
            $this->getJson(route('delivery.drivers.search', ['search' => $search]))
                ->assertOk()->assertJsonCount(1, 'drivers')->assertJsonPath('drivers.0.id', $driver->id)
                ->assertJsonPath('drivers.0.label', 'Santos, Maria Cruz (SEARCH-002)');
        }
        $this->getJson(route('delivery.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(1, 'drivers');
        $driver->update(['is_active' => false]);
        $this->getJson(route('delivery.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(0, 'drivers');
        $this->getJson(route('delivery.drivers.search', ['search' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_driver_search_limits_results_and_preserves_selected_driver_on_validation_errors(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->personnel('SEARCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
        $driver = $this->personnel('LAST-DRIVER');
        $driver->update(['last_name' => 'Zzz']);
        $this->actingAs($this->administrator())->getJson(route('delivery.drivers.search'))
            ->assertOk()->assertJsonCount(20, 'drivers');
        $this->withSession(['_old_input' => ['driver_id' => $driver->id]])->get(route('delivery.index'))
            ->assertOk()->assertSee('LAST-DRIVER')->assertSee('Search drivers');
    }

    public function test_driver_search_requires_authentication_and_delivery_create_permission(): void
    {
        $this->getJson(route('delivery.drivers.search'))->assertUnauthorized();
        $user = User::create(['name' => 'Viewer', 'username' => 'driver-viewer', 'password' => 'test-password']);
        $this->actingAs($user)->getJson(route('delivery.drivers.search'))->assertForbidden();
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'delivery.view')->value('id')]);
        $this->getJson(route('delivery.drivers.search'))->assertForbidden();
    }

    public function test_dispatch_creates_one_detail_per_waybill_and_repeated_dispatch_is_safe(): void
    {
        $this->package('001234');
        $this->package('WB-002');
        $this->package('UNRELATED');
        $this->package('001234', true);
        $delivery = $this->manifest(['001234', 'WB-002']);
        DB::table('packages')->whereIn('waybill_number', $delivery->waybill_numbers)->where('is_deleted', 0)
            ->update(['package_status' => 'for_delivery_dispatch']);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($this->administrator())->post(route('delivery.dispatch', $delivery))
                ->assertRedirect(route('delivery.show', $delivery))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('delivery_details', 2);
        foreach ($delivery->waybill_numbers as $number) {
            $this->assertDatabaseHas('delivery_details', ['dm' => $delivery->id, 'waybill_number' => $number, 'status' => 'dispatched']);
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'delivery_dispatched']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'accepted_to_warehouse']);
        $this->assertNotNull(DB::table('delivery_details')->first()->created_at);
        $this->get(route('delivery.show', $delivery))->assertOk()->assertSee('dispatched')->assertDontSee('Dispatch Document Manifest');
    }

    public function test_dispatch_rejects_invalid_packages_without_creating_any_details(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $delivery = $this->manifest(['VALID', 'DELETED']);
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_delivery_dispatch']);
        $this->actingAs($this->administrator())->from(route('delivery.show', $delivery))
            ->post(route('delivery.dispatch', $delivery))->assertRedirect(route('delivery.show', $delivery))
            ->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('delivery_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_delivery_dispatch']);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'DELETED', 'package_status' => 'accepted_to_warehouse']);
    }

    public function test_delivery_requires_authentication_and_permissions(): void
    {
        $delivery = $this->manifest(['WB-001']);
        $this->get(route('delivery.index'))->assertRedirect(route('login'));
        $this->get(route('delivery.show', $delivery))->assertRedirect(route('login'));
        foreach (['delivery.waybills.add', 'delivery.store', 'delivery.dispatch'] as $route) {
            $this->post(route($route, $route === 'delivery.dispatch' ? $delivery : []))->assertRedirect(route('login'));
        }
        $user = User::create(['name' => 'Delivery Test', 'username' => 'delivery-test', 'password' => 'test-password']);
        $this->actingAs($user)->get(route('delivery.index'))->assertForbidden();
        $this->get(route('delivery.show', $delivery))->assertForbidden();
        foreach (['delivery.waybills.add', 'delivery.store', 'delivery.dispatch'] as $route) {
            $this->post(route($route, $route === 'delivery.dispatch' ? $delivery : []))->assertForbidden();
        }
        $this->delete(route('delivery.waybills.remove'))->assertForbidden();
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
            'package_status' => 'accepted_to_warehouse', 'buyer_name' => 'Test Buyer',
            'buyer_full_address' => 'Test Address', 'is_deleted' => $deleted,
        ]);
    }

    private function manifest(array $numbers): Delivery
    {
        return Delivery::create(['waybill_numbers' => $numbers, 'created_by' => $this->administrator()->id,
            'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_dispatch_rechecks_package_status_without_creating_partial_details(): void
    {
        $this->package('VALID');
        $this->package('CHANGED');
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_delivery_dispatch']);
        $manifest = $this->manifest(['VALID', 'CHANGED']);
        $this->actingAs($this->administrator())->post(route('delivery.dispatch', $manifest))
            ->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('delivery_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_delivery_dispatch']);
    }

    public function test_dispatch_rechecks_driver_and_view_only_users_cannot_create_or_dispatch(): void
    {
        $this->package('VALID');
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_delivery_dispatch']);
        $manifest = $this->manifest(['VALID']);
        Personnel::where('id', $manifest->driver_id)->update(['is_active' => false]);
        $this->actingAs($this->administrator())->post(route('delivery.dispatch', $manifest))
            ->assertSessionHasErrors('driver_id');
        $this->assertDatabaseCount('delivery_details', 0);
        $user = User::create(['name' => 'Viewer', 'username' => 'delivery-viewer', 'password' => 'test-password']);
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'delivery.view')->value('id')]);
        $this->actingAs($user)->get(route('delivery.index'))->assertOk()->assertDontSee('Add Waybill Number/s');
        $this->get(route('delivery.show', $manifest))->assertOk()->assertDontSee('Dispatch Document Manifest');
        $this->post(route('delivery.store'))->assertForbidden();
        $this->post(route('delivery.dispatch', $manifest))->assertForbidden();
        $this->getJson(route('delivery.drivers.search'))->assertForbidden();
    }

    public function test_delivery_drafts_preserve_pickup_and_transfer_drafts(): void
    {
        $this->package('VALID');
        $this->actingAs($this->administrator())->withSession([
            'pickup.waybill_numbers' => ['PICKUP'], 'transfer.waybill_numbers' => ['TRANSFER'],
        ])->post(route('delivery.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
        $this->post(route('delivery.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')])
            ->assertSessionHasNoErrors()->assertSessionMissing('delivery.waybill_numbers')
            ->assertSessionHas('pickup.waybill_numbers', ['PICKUP'])->assertSessionHas('transfer.waybill_numbers', ['TRANSFER']);
    }

    public function test_all_active_packages_must_be_accepted_to_warehouse_before_adding(): void
    {
        $this->package('VALID');
        $this->package('MIXED');
        $this->package('MIXED');
        $packageId = DB::table('packages')->where('waybill_number', 'MIXED')->value('id');
        DB::table('packages')->where('id', $packageId)->update(['package_status' => 'pending']);
        $this->actingAs($this->administrator())->withSession(['delivery.waybill_numbers' => ['VALID']])
            ->from(route('delivery.index'))->post(route('delivery.waybills.add'), ['waybill_numbers' => 'MIXED'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('delivery.waybill_numbers', ['VALID']);
        $this->get(route('delivery.index'))->assertSee('Waybill MIXED must have package status accepted_to_warehouse');
        $this->package('VALID', true);
        DB::table('packages')->where('waybill_number', 'VALID')->where('is_deleted', 1)->update(['package_status' => 'pending']);
        $this->post(route('delivery.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
    }

    public function test_creation_rechecks_warehouse_status_and_keeps_the_draft_on_failure(): void
    {
        $this->package('CHANGED');
        $this->actingAs($this->administrator())->post(route('delivery.waybills.add'), ['waybill_numbers' => 'CHANGED'])
            ->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'pending']);
        $this->post(route('delivery.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('delivery.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'CHANGED', 'package_status' => 'pending']);
    }
}
