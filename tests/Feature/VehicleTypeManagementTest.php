<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VehicleTypeManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_vehicle_types(): void
    {
        $this->actingAs($this->administrator())->get(route('vehicle-types.index'))
            ->assertOk()
            ->assertSee('Vehicle Type Directory');

        $response = $this->actingAs($this->administrator())->post(route('vehicle-types.store'), [
            'vehicle_type' => 'Cargo Truck',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('vehicle-types.index'));
        $this->assertDatabaseHas('vehicle_types', ['vehicle_type' => 'Cargo Truck', 'is_active' => true]);
    }

    public function test_vehicle_type_must_be_unique(): void
    {
        VehicleType::create(['vehicle_type' => 'Delivery Van', 'is_active' => true]);

        $this->actingAs($this->administrator())->post(route('vehicle-types.store'), [
            'vehicle_type' => 'Delivery Van',
            'is_active' => '1',
        ])->assertSessionHasErrors('vehicle_type');
    }

    public function test_administrator_can_update_and_delete_a_vehicle_type(): void
    {
        $vehicleType = VehicleType::create(['vehicle_type' => 'Pickup', 'is_active' => true]);

        $this->actingAs($this->administrator())->put(route('vehicle-types.update', $vehicleType), [
            'vehicle_type' => 'Service Pickup',
            'is_active' => '0',
        ])->assertRedirect(route('vehicle-types.index'));

        $this->assertDatabaseHas('vehicle_types', ['id' => $vehicleType->id, 'vehicle_type' => 'Service Pickup', 'is_active' => false]);

        $this->actingAs($this->administrator())->delete(route('vehicle-types.destroy', $vehicleType))->assertRedirect();
        $this->assertDatabaseMissing('vehicle_types', ['id' => $vehicleType->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
