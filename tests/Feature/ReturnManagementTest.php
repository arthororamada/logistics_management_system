<?php

namespace Tests\Feature;

use App\Models\ReturnManifest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithStatusLogs;
use Tests\TestCase;

class ReturnManagementTest extends TestCase
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
        (require database_path('migrations/2026_10_11_090000_add_return_permissions.php'))->up();
        // Use the resulting manifest schema; SQLite cannot drop the historical foreign key.
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'dispatched', 'completed'])->default('pending');
            $table->json('waybill_numbers');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_11_080000_create_return_details_table.php'))->up();
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

    public function test_administrator_can_open_return_from_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('return.index'))
            ->assertOk()->assertSee('Add Waybill Number/s')->assertSee('Create Document Manifest')
            ->assertDontSee('Save ReturnManifest');
    }

    public function test_bulk_entry_is_validated_accumulated_and_saved_only_on_create(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->package('UNRELATED');
        $this->package('001234', true);
        $this->actingAs($this->administrator())->post(route('return.waybills.add'), [
            'waybill_numbers' => "001234\r\nWB-002, WB-003;WB-004\tWB-005\n001234"])->assertRedirect(route('return.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('return.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005']);
        $this->assertSame(0, ReturnManifest::count());
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'package_status' => 'for_return']);
        $this->post(route('return.waybills.add'), ['waybill_numbers' => '001234'])->assertSessionHasNoErrors();
        $this->get(route('return.index'))->assertOk()->assertSee('001234')->assertSee('Remove');
        $response = $this->post(route('return.store'));
        $returnManifest = ReturnManifest::firstOrFail();
        $response->assertRedirect(route('return.show', $returnManifest))->assertSessionHasNoErrors()
            ->assertSessionMissing('return.waybill_numbers');
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $returnManifest->waybill_numbers);
        $this->assertSame($this->administrator()->id, $returnManifest->created_by);
        $this->assertSame('pending', $returnManifest->status);
        $this->assertDatabaseCount('return_details', 0);
        $this->assertDatabaseCount('status_logs', 5);
        foreach ($returnManifest->waybill_numbers as $number) {
            $this->assertStatusLogged($number, 'for_return_dispatch', $this->administrator()->id);
            $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'is_deleted' => null, 'package_status' => 'for_return_dispatch']);
        }
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNRELATED', 'package_status' => 'for_return']);
        $this->assertDatabaseHas('packages', ['waybill_number' => '001234', 'is_deleted' => '2026-01-01 00:00:00', 'package_status' => 'for_return']);
        $this->get(route('return.show', $returnManifest))->assertOk()->assertDontSee('Dispatch Document Manifest')->assertSee('Test Buyer')->assertDontSee('Driver:');
    }

    public function test_unknown_and_deleted_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        $this->package('VALID');
        $this->package('DELETED', true);
        $this->actingAs($this->administrator())->withSession(['return.waybill_numbers' => ['VALID']])
            ->from(route('return.index'))->post(route('return.waybills.add'), [
                'waybill_numbers' => 'VALID UNKNOWN DELETED'])->assertRedirect(route('return.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('return.waybill_numbers', ['VALID']);
        $this->get(route('return.index'))->assertSee('Waybill UNKNOWN')->assertSee('Waybill DELETED');
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_invalid_bulk_entries_do_not_add_numbers(): void
    {
        foreach (['', " , ; \n", str_repeat('A', 101), implode("\n", range(1, 1001)), ['WB-001']] as $input) {
            $this->actingAs($this->administrator())->from(route('return.index'))
                ->post(route('return.waybills.add'), ['waybill_numbers' => $input])
                ->assertRedirect(route('return.index'))->assertSessionHasErrors('waybill_numbers')
                ->assertSessionMissing('return.waybill_numbers');
        }
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_dispatched_waybills_reject_the_batch_and_preserve_the_draft(): void
    {
        foreach (['VALID', 'NEW', 'DISPATCHED-001', 'DISPATCHED-002'] as $number) {
            $this->package($number);
        }
        $manifest = $this->manifest(['DISPATCHED-001', 'DISPATCHED-002']);
        foreach ($manifest->waybill_numbers as $number) {
            DB::table('return_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => 'dispatched']);
        }
        $this->actingAs($this->administrator())->withSession(['return.waybill_numbers' => ['VALID']])
            ->from(route('return.index'))->post(route('return.waybills.add'), [
                'waybill_numbers' => 'NEW DISPATCHED-001 DISPATCHED-002'])->assertRedirect(route('return.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('return.waybill_numbers', ['VALID']);
        $this->get(route('return.index'))->assertSee('Waybill DISPATCHED-001 has already been dispatched')
            ->assertSee('Waybill DISPATCHED-002 has already been dispatched');
        $this->assertDatabaseCount('returns', 1);
    }

    public function test_other_return_detail_statuses_do_not_block_adding_waybills(): void
    {
        $numbers = ['FAILED', 'CANCELLED', 'SUCCESS'];
        $manifest = $this->manifest($numbers);
        foreach (['FAILED' => 'failed', 'CANCELLED' => 'cancelled', 'SUCCESS' => 'success'] as $number => $status) {
            $this->package($number);
            DB::table('return_details')->insert(['dm' => $manifest->id, 'waybill_number' => $number, 'status' => $status]);
        }
        $this->actingAs($this->administrator())->post(route('return.waybills.add'), ['waybill_numbers' => implode(',', $numbers)])
            ->assertRedirect(route('return.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('return.waybill_numbers', $numbers);
    }

    public function test_dispatched_waybills_are_rechecked_before_creation(): void
    {
        $this->package('WB-001');
        $dispatched = $this->manifest(['WB-001']);
        DB::table('return_details')->insert(['dm' => $dispatched->id, 'waybill_number' => 'WB-001', 'status' => 'dispatched']);
        $this->actingAs($this->administrator())->withSession(['return.waybill_numbers' => ['WB-001']])
            ->from(route('return.index'))->post(route('return.store'))->assertSessionHasErrors('waybill_numbers')->assertSessionHas('return.waybill_numbers', ['WB-001']);
        $this->assertDatabaseCount('returns', 1);
    }

    public function test_waybills_can_be_removed_from_the_draft(): void
    {
        $this->actingAs($this->administrator())->withSession(['return.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('return.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('return.index'))->assertSessionHas('return.waybill_numbers', ['WB-002']);
    }

    public function test_create_requires_a_valid_draft_and_revalidates_packages(): void
    {
        $this->actingAs($this->administrator())->from(route('return.index'))
            ->post(route('return.store'), ['waybill_numbers' => 'BYPASS'])->assertSessionHasErrors('waybill_numbers');
        $this->package('DELETED', true);
        $this->withSession(['return.waybill_numbers' => ['DELETED']])->post(route('return.store'))
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('return.waybill_numbers', ['DELETED']);
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_return_requires_authentication_and_permissions(): void
    {
        $returnManifest = $this->manifest(['WB-001']);
        $this->get(route('return.index'))->assertRedirect(route('login'));
        $this->get(route('return.show', $returnManifest))->assertRedirect(route('login'));
        foreach (['return.waybills.add', 'return.store'] as $route) {
            $this->post(route($route))->assertRedirect(route('login'));
        }
        $user = User::create(['name' => 'ReturnManifest Test', 'username' => 'return-test', 'password' => 'test-password']);
        $this->actingAs($user)->get(route('return.index'))->assertForbidden();
        $this->get(route('return.show', $returnManifest))->assertForbidden();
        foreach (['return.waybills.add', 'return.store'] as $route) {
            $this->post(route($route))->assertForbidden();
        }
        $this->delete(route('return.waybills.remove'))->assertForbidden();
    }

    public function test_document_manifest_dispatch_is_removed(): void
    {
        $this->package('VALID');
        $manifest = $this->manifest(['VALID']);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('return.dispatch'));
        $this->actingAs($this->administrator())->get(route('return.show', $manifest))
            ->assertOk()->assertDontSee('Dispatch Document Manifest');
        $this->post('/document-manifests/return/'.$manifest->id.'/dispatch')->assertNotFound();
        $this->assertDatabaseCount('return_details', 0);
        $this->assertDatabaseCount('status_logs', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'VALID', 'package_status' => 'for_return']);
    }

    public function test_existing_manifest_details_remain_readable(): void
    {
        $this->package('HISTORICAL');
        $manifest = $this->manifest(['HISTORICAL']);
        DB::table('return_details')->insert(['dm' => $manifest->id, 'waybill_number' => 'HISTORICAL', 'status' => 'dispatched']);
        $this->actingAs($this->administrator())->get(route('return.show', $manifest))
            ->assertOk()->assertSee('HISTORICAL')->assertSee('Dispatched')->assertDontSee('Dispatch Document Manifest');
        $this->assertDatabaseCount('return_details', 1);
    }

    private function package(string $number, bool $deleted = false): void
    {
        DB::table('packages')->insert([
            'waybill_number' => $number, 'package_id' => 'PKG-'.$number,
            'package_status' => 'for_return', 'buyer_name' => 'Test Buyer',
            'buyer_full_address' => 'Test Address', 'is_deleted' => $deleted ? '2026-01-01 00:00:00' : null,
        ]);
    }

    private function manifest(array $numbers): ReturnManifest
    {
        return ReturnManifest::create(['waybill_numbers' => $numbers, 'created_by' => $this->administrator()->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_view_only_users_cannot_create(): void
    {
        $this->package('VALID');
        DB::table('packages')->where('waybill_number', 'VALID')->update(['package_status' => 'for_return_dispatch']);
        $manifest = $this->manifest(['VALID']);
        $user = User::create(['name' => 'Viewer', 'username' => 'return-viewer', 'password' => 'test-password']);
        $roleId = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'return.view')->value('id')]);
        $this->actingAs($user)->get(route('return.index'))->assertOk()->assertDontSee('Add Waybill Number/s');
        $this->get(route('return.show', $manifest))->assertOk()->assertDontSee('Dispatch Document Manifest');
        $this->post(route('return.store'))->assertForbidden();
    }

    public function test_return_drafts_preserve_pickup_and_transfer_drafts(): void
    {
        $this->package('VALID');
        $this->actingAs($this->administrator())->withSession([
            'pickup.waybill_numbers' => ['PICKUP'], 'transfer.waybill_numbers' => ['TRANSFER'],
            'delivery.waybill_numbers' => ['DELIVERY']])->post(route('return.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
        $this->post(route('return.store'))
            ->assertSessionHasNoErrors()->assertSessionMissing('return.waybill_numbers')
            ->assertSessionHas('pickup.waybill_numbers', ['PICKUP'])->assertSessionHas('transfer.waybill_numbers', ['TRANSFER'])
            ->assertSessionHas('delivery.waybill_numbers', ['DELIVERY']);
    }

    public function test_all_active_packages_must_be_for_return_before_adding(): void
    {
        $this->package('VALID');
        $this->package('MIXED');
        $this->package('MIXED');
        $packageId = DB::table('packages')->where('waybill_number', 'MIXED')->value('id');
        DB::table('packages')->where('id', $packageId)->update(['package_status' => 'pending']);
        $this->actingAs($this->administrator())->withSession(['return.waybill_numbers' => ['VALID']])
            ->from(route('return.index'))->post(route('return.waybills.add'), ['waybill_numbers' => 'MIXED'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('return.waybill_numbers', ['VALID']);
        $this->get(route('return.index'))->assertSee('Waybill MIXED must have package status for_return');
        $this->package('VALID', true);
        DB::table('packages')->where('waybill_number', 'VALID')->whereNotNull('is_deleted')->update(['package_status' => 'pending']);
        $this->post(route('return.waybills.add'), ['waybill_numbers' => 'VALID'])->assertSessionHasNoErrors();
    }

    public function test_creation_rechecks_return_status_and_keeps_the_draft_on_failure(): void
    {
        $this->package('CHANGED');
        $this->actingAs($this->administrator())->post(route('return.waybills.add'), ['waybill_numbers' => 'CHANGED'])
            ->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'pending']);
        $this->post(route('return.store'))
            ->assertSessionHasErrors('waybill_numbers')->assertSessionHas('return.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseCount('return_details', 0);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'CHANGED', 'package_status' => 'pending']);
    }
}
