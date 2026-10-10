<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_the_warehouse_directory(): void
    {
        $response = $this->actingAs($this->administrator())->get(route('warehouses.index'));

        $response->assertOk()->assertSee('Warehouse Directory');
    }

    public function test_administrator_can_create_a_warehouse(): void
    {
        $response = $this->actingAs($this->administrator())->post(route('warehouses.store'), [
            'warehouse_code' => 'wh-mnl-01',
            'warehouse_description' => 'Manila Central Warehouse',
            'longitude' => '120.9842195',
            'latitude' => '14.5995124',
            'complete_address' => '100 Logistics Avenue, Port Area',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'country' => 'Philippines',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('warehouses.index'));
        $this->assertDatabaseHas('warehouses', [
            'warehouse_code' => 'WH-MNL-01',
            'warehouse_description' => 'Manila Central Warehouse',
            'is_active' => true,
        ]);
    }

    public function test_coordinates_must_be_within_valid_ranges(): void
    {
        $response = $this->actingAs($this->administrator())->from(route('warehouses.create'))->post(route('warehouses.store'), [
            'warehouse_code' => 'WH-INVALID',
            'warehouse_description' => 'Invalid Coordinates',
            'longitude' => '181',
            'latitude' => '-91',
            'complete_address' => 'Test address',
            'city' => 'Test City',
            'province' => 'Test Province',
            'country' => 'Test Country',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('warehouses.create'));
        $response->assertSessionHasErrors(['longitude', 'latitude']);
        $this->assertDatabaseMissing('warehouses', ['warehouse_code' => 'WH-INVALID']);
    }

    public function test_administrator_can_update_and_delete_a_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'warehouse_code' => 'WH-CEB-01',
            'warehouse_description' => 'Cebu Warehouse',
            'longitude' => '123.8854000',
            'latitude' => '10.3157000',
            'complete_address' => 'Cebu Port Area',
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'country' => 'Philippines',
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($this->administrator())->put(route('warehouses.update', $warehouse), [
            'warehouse_code' => 'WH-CEB-02',
            'warehouse_description' => 'Cebu South Warehouse',
            'longitude' => '123.8854000',
            'latitude' => '10.3157000',
            'complete_address' => 'South Road Properties',
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'country' => 'Philippines',
            'is_active' => '0',
        ]);

        $updateResponse->assertRedirect(route('warehouses.index'));
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'warehouse_code' => 'WH-CEB-02', 'is_active' => false]);

        $deleteResponse = $this->actingAs($this->administrator())->delete(route('warehouses.destroy', $warehouse));

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
