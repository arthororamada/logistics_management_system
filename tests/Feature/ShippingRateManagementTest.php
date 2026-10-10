<?php

namespace Tests\Feature;

use App\Models\ShippingRate;
use App\Models\ShippingLine;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShippingRateManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shipping_rates_directory_returns_datatables_json(): void
    {
        ShippingLine::firstOrCreate(['shipping_line_name' => 'Oceanic Lines'], ['billing_address' => 'Harbor Road', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => true]);
        VehicleType::firstOrCreate(['vehicle_type' => 'Truck'], ['is_active' => true]);
        ShippingRate::create(['shipping_line_name' => 'Oceanic Lines', 'port_origin' => 'Manila', 'port_destination' => 'Cebu', 'vehicle_type' => 'Truck', 'shippine_rate' => 12500, 'is_active' => true]);
        $response = $this->actingAs($this->administrator())->getJson(route('shipping-rates.index', ['draw' => 1, 'start' => 0, 'length' => 25]));
        $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])->assertJsonFragment(['shipping_line_name' => 'Oceanic Lines']);
    }

    public function test_administrator_can_create_update_and_delete_a_shipping_rate(): void
    {
        ShippingLine::firstOrCreate(['shipping_line_name' => 'Pacific Lines'], ['billing_address' => 'Harbor Road', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => true]);
        VehicleType::firstOrCreate(['vehicle_type' => 'Van'], ['is_active' => true]);
        $payload = ['shipping_line_name' => 'Pacific Lines', 'port_origin' => 'Batangas', 'port_destination' => 'Iloilo', 'vehicle_type' => 'Van', 'shippine_rate' => '8500.00', 'is_active' => '1'];
        $this->actingAs($this->administrator())->post(route('shipping-rates.store'), $payload)->assertRedirect(route('shipping-rates.index'));
        $rate = ShippingRate::where('shipping_line_name', 'Pacific Lines')->firstOrFail();
        $this->actingAs($this->administrator())->put(route('shipping-rates.update', $rate), [...$payload, 'shippine_rate' => '9000.00', 'is_active' => '0'])->assertRedirect(route('shipping-rates.index'));
        $this->assertDatabaseHas('shipping_rates', ['id' => $rate->id, 'shippine_rate' => '9000.00', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('shipping-rates.destroy', $rate))->assertRedirect();
        $this->assertDatabaseMissing('shipping_rates', ['id' => $rate->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
