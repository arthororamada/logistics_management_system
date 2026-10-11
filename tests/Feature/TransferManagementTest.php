<?php

namespace Tests\Feature;

use App\Models\Transfer;
use App\Models\Personnel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransferManagementTest extends TestCase
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
        (require database_path('migrations/2026_10_11_010000_create_transfers_table.php'))->up();
        (require database_path('migrations/2026_09_06_020000_create_warehouses_table.php'))->up();
        (require database_path('migrations/2026_10_11_030000_add_transfer_permissions.php'))->up();
        $this->warehouse('WH-DEST');
        $this->personnel('DRIVER-001');
        (require database_path('migrations/2026_10_11_020000_create_transfer_details_table.php'))->up();
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

    public function test_administrator_can_open_transfer_from_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('transfer.index'))
            ->assertOk()->assertSee('Add Waybill Number/s')->assertSee('Create Document Manifest')
            ->assertDontSee('Save Transfer');
    }

    public function test_bulk_entry_is_validated_accumulated_and_saved_only_on_create(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->package('UNRELATED');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->post(route('transfer.waybills.add'), [
            'waybill_numbers' => "001234\r\nWB-002, WB-003;WB-004\tWB-005\n001234",
        ])->assertRedirect(route('transfer.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('transfer.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005']);
        $this->assertSame(0, Transfer::count());
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'package_status' => 'accepted_to_warehouse']);
        $this->post(route('transfer.waybills.add'), ['waybill_numbers' => '001234'])->assertSessionHasNoErrors();
        $this->get(route('transfer.index'))->assertOk()->assertSee('001234')->assertSee('Remove');
        $response = $this->post(route('transfer.store'), ['warehouse_destination' => 'WH-DEST', 'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id')]);
        $transfer = Transfer::firstOrFail();
        $response->assertRedirect(route('transfer.show', $transfer))->assertSessionHasNoErrors()
            ->assertSessionMissing('transfer.waybill_numbers');
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $transfer->waybill_numbers);
        $this->assertSame($this->administrator()->id, $transfer->created_by);
        $this->assertSame('WH-DEST', $transfer->warehouse_destination);
        $this->assertSame(Personnel::where('employee_id', 'DRIVER-001')->value('id'), $transfer->driver_id);
        $this->assertDatabaseCount('transfer_details', 0);
        foreach ($transfer->waybill_numbers as $number) {
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'accepted_to_warehouse']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'accepted_to_warehouse']);
        $this->get(route('transfer.show', $transfer))->assertOk()->assertSee('Dispatch Document Manifest')->assertSee('Test Buyer')->assertSee('DRIVER-001')->assertSee('Manila Destination');
    }

    public function test_unknown_and_deleted_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
            ->from(route('transfer.index'))->post(route('transfer.waybills.add'), [
                'waybill_numbers' => 'VALID UNKNOWN DELETED',
            ])->assertRedirect(route('transfer.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('transfer.waybill_numbers', ['VALID']);
        $this->get(route('transfer.index'))->assertSee('Waybill UNKNOWN')->assertSee('Waybill DELETED');
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_invalid_bulk_entries_do_not_add_numbers(): void
    {
        foreach (['', " , ; \n", str_repeat('A', 101), implode("\n", range(1, 1001)), ['WB-001']] as $input) {
            $this->actingAs($this->administrator())->from(route('transfer.index'))
                ->post(route('transfer.waybills.add'), ['waybill_numbers' => $input])
                ->assertRedirect(route('transfer.index'))->assertSessionHasErrors('waybill_numbers')
                ->assertSessionMissing('transfer.waybill_numbers');
        }
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_dispatched_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        foreach (['VALID', 'NEW', 'DISPATCHED-001', 'DISPATCHED-002'] as $number) {
            $this->package($number);
        }
        $manifest = $this->manifest(['DISPATCHED-001', 'DISPATCHED-002']);
        foreach ($manifest->waybill_numbers as $number) {
            DB::table('transfer_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => 'dispatched']);
        }
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
            ->from(route('transfer.index'))->post(route('transfer.waybills.add'), [
                'waybill_numbers' => 'NEW DISPATCHED-001 DISPATCHED-002',
            ])->assertRedirect(route('transfer.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('transfer.waybill_numbers', ['VALID']);
        $this->get(route('transfer.index'))->assertSee('Waybill DISPATCHED-001 has already been dispatched')
            ->assertSee('Waybill DISPATCHED-002 has already been dispatched');
        $this->assertDatabaseCount('transfers', 1);
    }

    public function test_other_transfer_detail_statuses_do_not_block_adding_waybills(): void
    {
        $numbers = ['TRANSFERRED', 'CANCELLED'];
        $manifest = $this->manifest($numbers);
        foreach (['TRANSFERRED' => 'transferred', 'CANCELLED' => 'cancelled'] as $number => $status) {
            $this->package($number);
            DB::table('transfer_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => $status]);
        }
        $this->actingAs($this->administrator())->post(route('transfer.waybills.add'), ['waybill_numbers' => implode(',', $numbers)])
            ->assertRedirect(route('transfer.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('transfer.waybill_numbers', $numbers);
    }

    public function test_dispatched_waybills_are_rechecked_before_creation_and_dispatch(): void
    {
        $this->package('WB-001');
        $dispatched = $this->manifest(['WB-001']);
        DB::table('transfer_details')->insert(['dm' => $dispatched->id, 'waybill_number' => 'WB-001', 'status' => 'dispatched']);
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['WB-001']])
            ->from(route('transfer.index'))->post(route('transfer.store'), [
                'warehouse_destination' => 'WH-DEST', 'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'),
            ])->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['WB-001']);
        $this->assertDatabaseCount('transfers', 1);
        $pending = $this->manifest(['WB-001']);
        $this->from(route('transfer.show', $pending))->post(route('transfer.dispatch', $pending))
            ->assertRedirect(route('transfer.show', $pending))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 1);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'WB-001', 'package_status' => 'accepted_to_warehouse']);
    }

    public function test_waybills_can_be_removed_from_the_draft(): void
    {
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('transfer.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('transfer.index'))->assertSessionHas('transfer.waybill_numbers', ['WB-002']);
    }

    public function test_create_requires_a_valid_draft_and_revalidates_packages(): void
    {
        $driverId = Personnel::where('employee_id', 'DRIVER-001')->value('id');
        $this->actingAs($this->administrator())->from(route('transfer.index'))
            ->post(route('transfer.store'), ['waybill_numbers' => 'BYPASS', 'warehouse_destination' => 'WH-DEST', 'driver_id' => $driverId])->assertSessionHasErrors('waybill_numbers');
        $this->package('DELETED', true);
        $this->withSession(['transfer.waybill_numbers' => ['DELETED']])->post(route('transfer.store'), ['warehouse_destination' => 'WH-DEST', 'driver_id' => $driverId])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['DELETED']);
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_driver_dropdown_only_lists_active_drivers(): void
    {
        $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $this->personnel('HELPER-001', 'Helper');
        $this->personnel('LOWERCASE-DRIVER', 'driver');
        $this->actingAs($this->administrator())->get(route('transfer.index'))
            ->assertOk()->assertSee('name="driver_id"', false)->assertSee('DRIVER-001')
            ->assertSee('LOWERCASE-DRIVER')->assertDontSee('INACTIVE-DRIVER')->assertDontSee('HELPER-001');
    }

    public function test_creation_requires_an_existing_active_driver_and_preserves_draft_on_failure(): void
    {
        $inactive = $this->personnel('INACTIVE-DRIVER', 'Driver', false);
        $helper = $this->personnel('HELPER-001', 'Helper');
        $this->package('VALID');
        foreach ([null, 999999, $inactive->id, $helper->id, 'invalid'] as $driverId) {
            $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
                ->from(route('transfer.index'))->post(route('transfer.store'), ['warehouse_destination' => 'WH-DEST', 'driver_id' => $driverId])
                ->assertRedirect(route('transfer.index'))->assertSessionHasErrors('driver_id')
                ->assertSessionHas('transfer.waybill_numbers', ['VALID']);
        }
        $this->assertDatabaseCount('transfers', 0);
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
            $this->getJson(route('transfer.drivers.search', ['search' => $search]))
                ->assertOk()->assertJsonCount(1, 'drivers')->assertJsonPath('drivers.0.id', $driver->id)
                ->assertJsonPath('drivers.0.label', 'Santos, Maria Cruz (SEARCH-002)');
        }
        $this->getJson(route('transfer.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(1, 'drivers');
        $driver->update(['is_active' => false]);
        $this->getJson(route('transfer.drivers.search', ['search' => 'SEARCH']))->assertJsonCount(0, 'drivers');
        $this->getJson(route('transfer.drivers.search', ['search' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_driver_search_limits_results_and_preserves_selected_driver_on_validation_errors(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->personnel('SEARCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
        $driver = $this->personnel('LAST-DRIVER');
        $driver->update(['last_name' => 'Zzz']);
        $this->actingAs($this->administrator())->getJson(route('transfer.drivers.search'))
            ->assertOk()->assertJsonCount(20, 'drivers');
        $this->withSession(['_old_input' => ['warehouse_destination' => 'WH-DEST', 'driver_id' => $driver->id]])->get(route('transfer.index'))
            ->assertOk()->assertSee('LAST-DRIVER')->assertSee('Search drivers');
    }

    public function test_driver_search_requires_authentication_and_transfer_create_permission(): void
    {
        $this->getJson(route('transfer.drivers.search'))->assertUnauthorized();
        $user = User::create(['name' => 'Viewer', 'username' => 'driver-viewer', 'password' => 'test-password']);
        $this->actingAs($user)->getJson(route('transfer.drivers.search'))->assertForbidden();
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'transfer.view')->value('id')]);
        $this->getJson(route('transfer.drivers.search'))->assertForbidden();
    }

    public function test_dispatch_creates_one_detail_per_waybill_and_repeated_dispatch_is_safe(): void
    {
        $this->package('001234');
        $this->package('WB-002');
        $this->package('UNRELATED');
        $this->package('001234', true);
        $transfer = $this->manifest(['001234', 'WB-002']);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($this->administrator())->post(route('transfer.dispatch', $transfer))
                ->assertRedirect(route('transfer.show', $transfer))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('transfer_details', 2);
        foreach ($transfer->waybill_numbers as $number) {
            $this->assertDatabaseHas('transfer_details', ['dm' => $transfer->id, 'waybill_number' => $number, 'status' => 'dispatched']);
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => 0, 'package_status' => 'accepted_to_warehouse']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => 1, 'package_status' => 'accepted_to_warehouse']);
        $this->assertNotNull(DB::table('transfer_details')->first()->created_at);
        $this->get(route('transfer.show', $transfer))->assertOk()->assertSee('dispatched')->assertDontSee('Dispatch Document Manifest');
    }

    public function test_dispatch_rejects_invalid_packages_without_creating_any_details(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $transfer = $this->manifest(['VALID', 'DELETED']);
        $this->actingAs($this->administrator())->from(route('transfer.show', $transfer))
            ->post(route('transfer.dispatch', $transfer))->assertRedirect(route('transfer.show', $transfer))
            ->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'DELETED', 'package_status' => 'accepted_to_warehouse']);
    }

    public function test_transfer_requires_authentication_and_permissions(): void
    {
        $transfer = $this->manifest(['WB-001']);
        $this->get(route('transfer.index'))->assertRedirect(route('login'));
        $this->get(route('transfer.show', $transfer))->assertRedirect(route('login'));
        foreach (['transfer.waybills.add', 'transfer.store', 'transfer.dispatch'] as $route) {
            $this->post(route($route, $route === 'transfer.dispatch' ? $transfer : []))->assertRedirect(route('login'));
        }
        $user = User::create(['name' => 'Transfer Test', 'username' => 'transfer-test', 'password' => 'test-password']);
        $this->actingAs($user)->get(route('transfer.index'))->assertForbidden();
        $this->get(route('transfer.show', $transfer))->assertForbidden();
        foreach (['transfer.waybills.add', 'transfer.store', 'transfer.dispatch'] as $route) {
            $this->post(route($route, $route === 'transfer.dispatch' ? $transfer : []))->assertForbidden();
        }
        $this->delete(route('transfer.waybills.remove'))->assertForbidden();
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

    private function manifest(array $numbers): Transfer
    {
        return Transfer::create(['waybill_numbers' => $numbers, 'created_by' => $this->administrator()->id,
            'driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'), 'warehouse_destination' => 'WH-DEST']);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_all_active_packages_must_be_accepted_to_warehouse_before_adding(): void
    {
        $this->package('VALID');
        $this->package('MIXED');
        $this->package('MIXED');
        DB::table('packages')->where('waybill_number', 'MIXED')->orderBy('id')->limit(1)
            ->update(['package_status' => 'accepted_by_courier']);
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
            ->from(route('transfer.index'))->post(route('transfer.waybills.add'), ['waybill_numbers' => 'MIXED'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['VALID']);
        $this->get(route('transfer.index'))->assertSee('Waybill MIXED must have package status accepted_to_warehouse');
        $this->package('VALID', true);
        DB::table('packages')->where('waybill_number', 'VALID')->where('is_deleted', 1)
            ->update(['package_status' => 'pending']);
        $this->post(route('transfer.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
    }

    public function test_package_status_is_rechecked_before_creation_and_dispatch(): void
    {
        $this->package('CHANGED');
        $this->actingAs($this->administrator())->post(route('transfer.waybills.add'), ['waybill_numbers' => 'CHANGED'])
            ->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'pending']);
        $this->post(route('transfer.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'), 'warehouse_destination' => 'WH-DEST'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('transfers', 0);
        $this->package('VALID');
        $manifest = $this->manifest(['VALID', 'CHANGED']);
        $this->post(route('transfer.dispatch', $manifest))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'accepted_to_warehouse']);
    }

    private function warehouse(string $code, bool $active = true): Warehouse
    {
        return Warehouse::create([
            'warehouse_code' => $code, 'warehouse_description' => 'Manila Destination',
            'longitude' => 120.98, 'latitude' => 14.60, 'complete_address' => 'Test Address',
            'city' => 'Manila', 'province' => 'Metro Manila', 'country' => 'Philippines', 'is_active' => $active,
        ]);
    }

    public function test_warehouse_search_queries_active_destinations(): void
    {
        $this->warehouse('INACTIVE', false);
        $this->actingAs($this->administrator());
        foreach (['WH-DEST', 'Destination', 'Manila', 'Metro', 'Manila Destination'] as $term) {
            $this->getJson(route('transfer.warehouses.search', ['search' => $term]))
                ->assertOk()->assertJsonCount(1, 'warehouses')->assertJsonPath('warehouses.0.id', 'WH-DEST');
        }
        $this->getJson(route('transfer.warehouses.search', ['search' => 'INACTIVE']))->assertJsonCount(0, 'warehouses');
        Warehouse::where('warehouse_code', 'WH-DEST')->update(['is_active' => false]);
        $this->getJson(route('transfer.warehouses.search', ['search' => 'WH-DEST']))->assertJsonCount(0, 'warehouses');
        $this->getJson(route('transfer.warehouses.search', ['search' => str_repeat('a', 101)]))->assertUnprocessable();
    }

    public function test_warehouse_search_requires_create_permission_and_keeps_old_selected_destination(): void
    {
        $this->getJson(route('transfer.warehouses.search'))->assertUnauthorized();
        $user = User::create(['name' => 'Viewer', 'username' => 'warehouse-viewer', 'password' => 'test-password']);
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'transfer.view')->value('id')]);
        $this->actingAs($user)->getJson(route('transfer.warehouses.search'))->assertForbidden();
        $this->get(route('transfer.index'))->assertOk()->assertDontSee('Add Waybill Number/s');
        for ($i = 0; $i < 25; $i++) {
            $warehouse = $this->warehouse('WH-'.$i);
            $warehouse->update(['warehouse_description' => 'AAA '.$i]);
        }
        $this->actingAs($this->administrator())->getJson(route('transfer.warehouses.search'))->assertJsonCount(20, 'warehouses');
        $this->withSession(['_old_input' => ['warehouse_destination' => 'WH-DEST']])->get(route('transfer.index'))
            ->assertOk()->assertSee('Manila Destination')->assertSee('id="warehouse-picker-trigger"', false);
    }

    public function test_creation_requires_an_active_destination_and_preserves_draft(): void
    {
        $this->warehouse('INACTIVE', false);
        $this->package('VALID');
        foreach ([null, 'MISSING', 'INACTIVE'] as $code) {
            $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
                ->post(route('transfer.store'), ['driver_id' => Personnel::where('employee_id', 'DRIVER-001')->value('id'), 'warehouse_destination' => $code])
                ->assertSessionHasErrors('warehouse_destination')->assertSessionHas('transfer.waybill_numbers', ['VALID']);
        }
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_dispatch_rechecks_destination_and_driver_before_creating_details(): void
    {
        $this->package('VALID');
        $manifest = $this->manifest(['VALID']);
        Warehouse::where('warehouse_code', 'WH-DEST')->update(['is_active' => false]);
        $this->actingAs($this->administrator())->post(route('transfer.dispatch', $manifest))
            ->assertSessionHasErrors('warehouse_destination');
        $this->assertDatabaseCount('transfer_details', 0);
        Warehouse::where('warehouse_code', 'WH-DEST')->update(['is_active' => true]);
        Personnel::where('employee_id', 'DRIVER-001')->update(['is_active' => false]);
        $this->post(route('transfer.dispatch', $manifest))->assertSessionHasErrors('driver_id');
        $this->assertDatabaseCount('transfer_details', 0);
    }
}
