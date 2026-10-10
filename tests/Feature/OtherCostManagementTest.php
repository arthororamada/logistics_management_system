<?php

namespace Tests\Feature;

use App\Models\OtherCost;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OtherCostManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_other_costs(): void
    {
        $this->actingAs($this->administrator())->get(route('other-costs.index'))->assertOk()->assertSee('Other Cost Directory');
        $response = $this->actingAs($this->administrator())->post(route('other-costs.store'), ['cost_code' => 'toll_fee', 'cost_name' => 'Toll Fee', 'is_cash_release' => '1', 'is_active' => '1']);
        $response->assertRedirect(route('other-costs.index'));
        $this->assertDatabaseHas('other_costs', ['cost_code' => 'TOLL_FEE', 'cost_name' => 'Toll Fee', 'is_cash_release' => true, 'is_active' => true]);
    }

    public function test_cost_code_must_be_unique(): void
    {
        $code = 'TEST_'.str()->upper(str()->random(10));
        OtherCost::create(['cost_code' => $code, 'cost_name' => 'Existing Cost', 'is_cash_release' => false, 'is_active' => true]);
        $this->actingAs($this->administrator())->post(route('other-costs.store'), ['cost_code' => $code, 'cost_name' => 'Duplicate', 'is_cash_release' => '0', 'is_active' => '1'])->assertSessionHasErrors('cost_code');
    }

    public function test_administrator_can_update_and_delete_an_other_cost(): void
    {
        $otherCost = OtherCost::create(['cost_code' => 'FUEL_SURCHARGE', 'cost_name' => 'Fuel Surcharge', 'is_cash_release' => false, 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('other-costs.update', $otherCost), ['cost_code' => 'FUEL_CASH', 'cost_name' => 'Fuel Cash', 'is_cash_release' => '1', 'is_active' => '0'])->assertRedirect(route('other-costs.index'));
        $this->assertDatabaseHas('other_costs', ['id' => $otherCost->id, 'cost_code' => 'FUEL_CASH', 'is_cash_release' => true, 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('other-costs.destroy', $otherCost))->assertRedirect();
        $this->assertDatabaseMissing('other_costs', ['id' => $otherCost->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
