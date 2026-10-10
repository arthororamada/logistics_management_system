<?php

namespace Tests\Feature;

use App\Models\FuelSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FuelSupplierManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_fuel_suppliers(): void
    {
        $this->actingAs($this->administrator())->get(route('fuel-suppliers.index'))->assertOk()->assertSee('Fuel Supplier Directory');
        $response = $this->actingAs($this->administrator())->post(route('fuel-suppliers.store'), ['fuel_supplier_name' => 'Shell', 'price_per_liter' => '65.50', 'is_active' => '1']);
        $response->assertRedirect(route('fuel-suppliers.index'));
        $this->assertDatabaseHas('fuel_suppliers', ['fuel_supplier_name' => 'Shell', 'price_per_liter' => '65.50', 'is_active' => true]);
    }

    public function test_price_per_liter_cannot_be_negative(): void
    {
        $this->actingAs($this->administrator())->post(route('fuel-suppliers.store'), ['fuel_supplier_name' => 'Invalid Supplier '.str()->random(8), 'price_per_liter' => '-1', 'is_active' => '1'])->assertSessionHasErrors('price_per_liter');
    }

    public function test_administrator_can_update_and_delete_a_fuel_supplier(): void
    {
        $supplier = FuelSupplier::create(['fuel_supplier_name' => 'Caltex', 'price_per_liter' => 64.25, 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('fuel-suppliers.update', $supplier), ['fuel_supplier_name' => 'Caltex Updated', 'price_per_liter' => '66.00', 'is_active' => '0'])->assertRedirect(route('fuel-suppliers.index'));
        $this->assertDatabaseHas('fuel_suppliers', ['id' => $supplier->id, 'fuel_supplier_name' => 'Caltex Updated', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('fuel-suppliers.destroy', $supplier))->assertRedirect();
        $this->assertDatabaseMissing('fuel_suppliers', ['id' => $supplier->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
