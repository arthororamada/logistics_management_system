<?php

namespace Tests\Feature;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithStatusLogs;
use Tests\TestCase;

class TransferManagementTest extends TestCase
{
    use InteractsWithStatusLogs;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutVite();
        // This isolated manifest schema does not include the topbar maintenance tables.
        $this->mock(\App\Services\MaintenanceAlertService::class)
            ->shouldReceive('all')->andReturn(collect());

        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        User::create(['name' => 'Administrator', 'username' => 'admin', 'password' => 'test-password']);
        (require database_path('migrations/2026_08_31_010000_create_roles_and_permissions_tables.php'))->up();
        (require database_path('migrations/2026_10_11_030000_add_transfer_permissions.php'))->up();
        // Use the resulting manifest schema; SQLite cannot drop the historical foreign key.
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->json('waybill_numbers');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_11_020000_create_transfer_details_table.php'))->up();
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('package_id', 100);
            $table->string('package_status', 100);
            $table->string('buyer_name');
            $table->string('buyer_full_address', 100);
            $table->timestamp('is_deleted')->nullable();
        });
        $this->createStatusLogsTable();
    }

    public function test_administrator_can_open_transfer_from_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('transfer.index'))
            ->assertOk()->assertSee('Add Waybill Number/s')->assertSee('Create Document Manifest')
            ->assertDontSee('Save Transfer')->assertDontSee('name="driver_id"', false)->assertDontSee('name="warehouse_destination"', false);
    }

    public function test_bulk_entry_is_validated_accumulated_and_saved_only_on_create(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->package('UNRELATED');
        $this->package('001234');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->post(route('transfer.waybills.add'), [
            'waybill_numbers' => "001234\r\nWB-002, WB-003;WB-004\tWB-005\n001234"])->assertRedirect(route('transfer.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('transfer.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005']);
        $this->assertSame(0, Transfer::count());
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'package_status' => 'accepted_to_warehouse']);
        $this->post(route('transfer.waybills.add'), ['waybill_numbers' => '001234'])->assertSessionHasNoErrors();
        $this->get(route('transfer.index'))->assertOk()->assertSee('001234')->assertSee('Remove');
        $response = $this->post(route('transfer.store'));
        $transfer = Transfer::firstOrFail();
        $response->assertRedirect(route('transfer.show', $transfer))->assertSessionHasNoErrors()
            ->assertSessionMissing('transfer.waybill_numbers');
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $transfer->waybill_numbers);
        $this->assertSame($this->administrator()->id, $transfer->created_by);
        $this->assertDatabaseCount('transfer_details', 0);
        foreach ($transfer->waybill_numbers as $number) {
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => null, 'package_status' => 'for_transfer_dispatch']);
        }
        $this->assertSame(6, DB::table('packages')->where('package_status', 'for_transfer_dispatch')->count());
        foreach ($transfer->waybill_numbers as $number) {
            $this->assertStatusLogged($number, 'for_transfer_dispatch', $this->administrator()->id);
        }
        $this->assertDatabaseCount('status_logs', 5);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => '2026-01-01 00:00:00', 'package_status' => 'accepted_to_warehouse']);
        $this->get(route('transfer.show', $transfer))->assertOk()->assertSee('Dispatch Document Manifest')->assertSee('Test Buyer')->assertDontSee('Driver:')->assertDontSee('Destination warehouse:');
    }

    public function test_unknown_and_deleted_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['VALID']])
            ->from(route('transfer.index'))->post(route('transfer.waybills.add'), [
                'waybill_numbers' => 'VALID UNKNOWN DELETED'])->assertRedirect(route('transfer.index'))->assertSessionHasErrors('waybill_numbers')
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
                'waybill_numbers' => 'NEW DISPATCHED-001 DISPATCHED-002'])->assertRedirect(route('transfer.index'))->assertSessionHasErrors('waybill_numbers')
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
            ->from(route('transfer.index'))->post(route('transfer.store'))->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['WB-001']);
        $this->assertDatabaseCount('transfers', 1);
        $pending = $this->manifest(['WB-001']);
        DB::table('packages')->where('waybill_number', 'WB-001')->update(['package_status' => 'for_transfer_dispatch']);
        $this->from(route('transfer.show', $pending))->post(route('transfer.dispatch', $pending))
            ->assertRedirect(route('transfer.show', $pending))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 1);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'WB-001', 'package_status' => 'for_transfer_dispatch']);
    }

    public function test_waybills_can_be_removed_from_the_draft(): void
    {
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('transfer.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('transfer.index'))->assertSessionHas('transfer.waybill_numbers', ['WB-002']);
    }

    public function test_create_requires_a_valid_draft_and_revalidates_packages(): void
    {
        $this->actingAs($this->administrator())->from(route('transfer.index'))
            ->post(route('transfer.store'), ['waybill_numbers' => 'BYPASS'])->assertSessionHasErrors('waybill_numbers');
        $this->package('DELETED', true);
        $this->withSession(['transfer.waybill_numbers' => ['DELETED']])->post(route('transfer.store'))
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['DELETED']);
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_dispatch_creates_one_detail_per_waybill_and_repeated_dispatch_is_safe(): void
    {
        $this->package('001234');
        $this->package('WB-002');
        $this->package('UNRELATED');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->withSession(['transfer.waybill_numbers' => ['001234', 'WB-002']])
            ->post(route('transfer.store'))
            ->assertSessionHasNoErrors();
        $transfer = Transfer::firstOrFail();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($this->administrator())->post(route('transfer.dispatch', $transfer))
                ->assertRedirect(route('transfer.show', $transfer))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('transfer_details', 2);
        foreach ($transfer->waybill_numbers as $number) {
            $this->assertDatabaseHas('transfer_details', ['dm' => $transfer->id, 'waybill_number' => $number, 'status' => 'dispatched']);
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => null, 'package_status' => 'for_transfer_dispatch']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'accepted_to_warehouse']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => '2026-01-01 00:00:00', 'package_status' => 'accepted_to_warehouse']);
        $this->assertNotNull(DB::table('transfer_details')->first()->created_at);
        $this->get(route('transfer.show', $transfer))->assertOk()->assertSee('dispatched')->assertDontSee('Dispatch Document Manifest');
    }

    public function test_dispatch_rejects_invalid_packages_without_creating_any_details(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $transfer = $this->manifest(['VALID', 'DELETED']);
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_transfer_dispatch']);
        $this->actingAs($this->administrator())->from(route('transfer.show', $transfer))
            ->post(route('transfer.dispatch', $transfer))->assertRedirect(route('transfer.show', $transfer))
            ->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_transfer_dispatch']);
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

    private function package(string $number, bool $deleted = false): void
    {
        DB::table('packages')->insert([
            'waybill_number' => $number, 'package_id' => 'PKG-'.$number,
            'package_status' => 'accepted_to_warehouse', 'buyer_name' => 'Test Buyer',
            'buyer_full_address' => 'Test Address', 'is_deleted' => $deleted ? '2026-01-01 00:00:00' : null,
        ]);
    }

    private function manifest(array $numbers): Transfer
    {
        return Transfer::create(['waybill_numbers' => $numbers, 'created_by' => $this->administrator()->id]);
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
        DB::table('packages')->where('waybill_number', 'VALID')->whereNotNull('is_deleted')
            ->update(['package_status' => 'pending']);
        $this->post(route('transfer.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
    }

    public function test_package_status_is_rechecked_before_creation_and_dispatch(): void
    {
        $this->package('CHANGED');
        $this->actingAs($this->administrator())->post(route('transfer.waybills.add'), ['waybill_numbers' => 'CHANGED'])
            ->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'pending']);
        $this->post(route('transfer.store'))
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('transfer.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('transfers', 0);
        $this->package('VALID');
        $manifest = $this->manifest(['VALID', 'CHANGED']);
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_transfer_dispatch']);
        $this->post(route('transfer.dispatch', $manifest))->assertSessionHasErrors('waybill_numbers');
        $this->assertDatabaseCount('transfer_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_transfer_dispatch']);
    }
}
