<?php

namespace Tests\Feature;

use App\Models\ShippingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShippingLineManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_shipping_lines(): void
    {
        $this->actingAs($this->administrator())->get(route('shipping-lines.index'))->assertOk()->assertSee('Shipping Line Directory');
        $response = $this->actingAs($this->administrator())->post(route('shipping-lines.store'), ['shipping_line_name' => 'Oceanic Lines', 'billing_address' => '1 Harbor Road', 'focal_person' => 'Ana Cruz', 'contact_number' => '09171234567', 'is_active' => '1']);
        $response->assertRedirect(route('shipping-lines.index'));
        $this->assertDatabaseHas('shipping_lines', ['shipping_line_name' => 'Oceanic Lines', 'focal_person' => 'Ana Cruz', 'is_active' => true]);
    }

    public function test_shipping_line_name_must_be_unique(): void
    {
        $name = 'Test Shipping '.str()->random(10);
        ShippingLine::create(['shipping_line_name' => $name, 'billing_address' => 'Address', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => true]);
        $this->actingAs($this->administrator())->post(route('shipping-lines.store'), ['shipping_line_name' => $name, 'billing_address' => 'Address', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => '1'])->assertSessionHasErrors('shipping_line_name');
    }

    public function test_administrator_can_update_and_delete_a_shipping_line(): void
    {
        $line = ShippingLine::create(['shipping_line_name' => 'Pacific Line', 'billing_address' => 'Old Address', 'focal_person' => 'Old Contact', 'contact_number' => '123', 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('shipping-lines.update', $line), ['shipping_line_name' => 'Pacific Updated', 'billing_address' => 'New Address', 'focal_person' => 'New Contact', 'contact_number' => '456', 'is_active' => '0'])->assertRedirect(route('shipping-lines.index'));
        $this->assertDatabaseHas('shipping_lines', ['id' => $line->id, 'shipping_line_name' => 'Pacific Updated', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('shipping-lines.destroy', $line))->assertRedirect();
        $this->assertDatabaseMissing('shipping_lines', ['id' => $line->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
