<?php

namespace Tests\Feature;

use App\Models\Inbound;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InboundManagementTest extends TestCase
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
        (require database_path('migrations/2026_10_11_000000_create_inbounds_table.php'))->up();
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

    public function test_inbound_is_available_under_document_manifests(): void
    {
        $this->actingAs($this->administrator())->get(route('inbound.index'))
            ->assertOk()->assertSee('Document Manifests')->assertSee('Inbound')
            ->assertSee('Add Waybill Number/s')->assertSee('accepted_by_courier');
        $this->assertDatabaseHas('permissions', ['slug' => 'inbound.view', 'group' => 'Document Manifests']);
        $this->assertDatabaseHas('permissions', ['slug' => 'inbound.create']);
    }

    public function test_accepted_waybills_are_accumulated_deduplicated_and_saved(): void
    {
        foreach (['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'] as $number) {
            $this->package($number);
        }
        $this->actingAs($this->administrator())->withSession(['pickup.waybill_numbers' => ['PICKUP-ONLY']])
            ->post(route('inbound.waybills.add'), ['waybill_numbers' => "001234\r\nWB-002,WB-003;WB-004\t001234"])
            ->assertRedirect(route('inbound.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('inbound.waybill_numbers', ['001234', 'WB-002', 'WB-003', 'WB-004']);
        $this->assertDatabaseCount('inbounds', 0);
        $this->post(route('inbound.waybills.add'), ['waybill_numbers' => 'WB-004 WB-005'])->assertSessionHasNoErrors();
        $this->get(route('inbound.index'))->assertOk()->assertSee('001234')->assertSee('WB-005');
        $response = $this->post(route('inbound.store'));
        $inbound = Inbound::firstOrFail();
        $response->assertRedirect(route('inbound.show', $inbound))->assertSessionHasNoErrors()
            ->assertSessionMissing('inbound.waybill_numbers')->assertSessionHas('pickup.waybill_numbers', ['PICKUP-ONLY']);
        $this->assertSame(['001234', 'WB-002', 'WB-003', 'WB-004', 'WB-005'], $inbound->waybill_numbers);
        $this->assertSame($this->administrator()->id, $inbound->created_by);
        $this->get(route('inbound.show', $inbound))->assertOk()->assertSee('001234')->assertSee('Test Buyer');
        $this->get(route('inbound.index'))->assertSee(route('inbound.show', $inbound));
        $this->assertSame(5, DB::table('packages')->where('package_status', 'accepted_by_courier')->count());
    }

    public function test_missing_deleted_and_wrong_status_waybills_reject_the_batch(): void
    {
        $this->package('VALID');
        $this->package('NEW');
        $this->package('DELETED', 'accepted_by_courier', true);
        $this->package('WRONG', 'pickup_dispatched');
        $this->actingAs($this->administrator())->withSession(['inbound.waybill_numbers' => ['VALID']])
            ->from(route('inbound.index'))->post(route('inbound.waybills.add'), [
                'waybill_numbers' => 'NEW MISSING DELETED WRONG',
            ])->assertRedirect(route('inbound.index'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('inbound.waybill_numbers', ['VALID']);
        $this->get(route('inbound.index'))->assertSee('Waybill MISSING')->assertSee('Waybill DELETED')
            ->assertSee('Waybill WRONG must have package status accepted_by_courier');
        $this->assertDatabaseCount('inbounds', 0);
    }

    public function test_all_packages_for_a_waybill_must_have_the_required_status(): void
    {
        $this->package('MULTI');
        $this->package('MULTI', 'pending');
        $this->actingAs($this->administrator())->from(route('inbound.index'))
            ->post(route('inbound.waybills.add'), ['waybill_numbers' => 'MULTI'])
            ->assertSessionHasErrors('waybill_numbers')->assertSessionMissing('inbound.waybill_numbers');
    }

    public function test_invalid_entries_are_rejected(): void
    {
        foreach (['', " , ; \n", str_repeat('A', 101), implode("\n", range(1, 1001)), ['WB-001']] as $input) {
            $this->actingAs($this->administrator())->from(route('inbound.index'))
                ->post(route('inbound.waybills.add'), ['waybill_numbers' => $input])
                ->assertRedirect(route('inbound.index'))->assertSessionHasErrors('waybill_numbers')
                ->assertSessionMissing('inbound.waybill_numbers');
        }
    }

    public function test_waybills_can_be_removed(): void
    {
        $this->actingAs($this->administrator())->withSession(['inbound.waybill_numbers' => ['001234', 'WB-002']])
            ->delete(route('inbound.waybills.remove'), ['waybill_number' => '001234'])
            ->assertRedirect(route('inbound.index'))->assertSessionHas('inbound.waybill_numbers', ['WB-002']);
    }

    public function test_creation_revalidates_status_and_requires_a_draft(): void
    {
        $this->actingAs($this->administrator())->from(route('inbound.index'))
            ->post(route('inbound.store'), ['waybill_numbers' => 'BYPASS'])->assertSessionHasErrors('waybill_numbers');
        $this->package('CHANGED');
        $this->post(route('inbound.waybills.add'), ['waybill_numbers' => 'CHANGED'])->assertSessionHasNoErrors();
        DB::table('packages')->where('waybill_number', 'CHANGED')->update(['package_status' => 'pending']);
        $this->post(route('inbound.store'))->assertSessionHasErrors('waybill_numbers')
            ->assertSessionHas('inbound.waybill_numbers', ['CHANGED']);
        $this->assertDatabaseCount('inbounds', 0);
    }

    public function test_routes_require_authentication_and_permissions(): void
    {
        $inbound = Inbound::create(['waybill_numbers' => ['WB-001'], 'created_by' => $this->administrator()->id]);
        foreach (['inbound.index', 'inbound.show'] as $route) {
            $this->get(route($route, $route === 'inbound.show' ? $inbound : []))->assertRedirect(route('login'));
        }
        foreach (['inbound.store', 'inbound.waybills.add'] as $route) {
            $this->post(route($route))->assertRedirect(route('login'));
        }
        $this->delete(route('inbound.waybills.remove'))->assertRedirect(route('login'));
        $user = User::create(['name' => 'Staff', 'username' => 'inbound-staff', 'password' => 'test-password']);
        $this->actingAs($user)->get(route('inbound.index'))->assertForbidden();
        $this->get(route('inbound.show', $inbound))->assertForbidden();
        foreach (['inbound.store', 'inbound.waybills.add'] as $route) {
            $this->post(route($route))->assertForbidden();
        }
        $this->delete(route('inbound.waybills.remove'))->assertForbidden();

        $staffRole = DB::table('roles')->where('slug', 'staff')->value('id');
        DB::table('role_user')->insert(['role_id' => $staffRole, 'user_id' => $user->id]);
        DB::table('permission_role')->insert(['role_id' => $staffRole, 'permission_id' => DB::table('permissions')->where('slug', 'inbound.view')->value('id')]);
        $this->get(route('inbound.index'))->assertOk()->assertDontSee('Add Waybill Number/s');
        $this->get(route('inbound.show', $inbound))->assertOk();
        $this->post(route('inbound.store'))->assertForbidden();
        $this->post(route('inbound.waybills.add'))->assertForbidden();
        $this->delete(route('inbound.waybills.remove'))->assertForbidden();
    }

    private function package(string $number, string $status = 'accepted_by_courier', bool $deleted = false): void
    {
        DB::table('packages')->insert([
            'waybill_number' => $number, 'package_id' => 'PKG-'.$number,
            'package_status' => $status, 'buyer_name' => 'Test Buyer',
            'buyer_full_address' => 'Test Address', 'is_deleted' => $deleted,
        ]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
