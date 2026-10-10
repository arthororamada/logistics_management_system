<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VehicleGroupManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_vehicle_groups(): void
    {
        $this->actingAs($this->administrator())->get(route('vehicle-groups.index'))
            ->assertOk()
            ->assertSee('Vehicle Group Directory');

        $response = $this->actingAs($this->administrator())->post(route('vehicle-groups.store'), [
            'group_code' => 'heavy_trucks',
            'vehicle_group_description' => 'Heavy-Duty Trucks',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('vehicle-groups.index'));
        $this->assertDatabaseHas('vehicle_groups', [
            'group_code' => 'HEAVY_TRUCKS',
            'vehicle_group_description' => 'Heavy-Duty Trucks',
            'is_active' => true,
        ]);
    }

    public function test_group_code_must_be_unique(): void
    {
        VehicleGroup::create([
            'group_code' => 'VANS',
            'vehicle_group_description' => 'Delivery Vans',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())->post(route('vehicle-groups.store'), [
            'group_code' => 'vans',
            'vehicle_group_description' => 'Other Vans',
            'is_active' => '1',
        ])->assertSessionHasErrors('group_code');
    }

    public function test_administrator_can_update_and_delete_a_vehicle_group(): void
    {
        $vehicleGroup = VehicleGroup::create([
            'group_code' => 'SERVICE',
            'vehicle_group_description' => 'Service Vehicles',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())->put(route('vehicle-groups.update', $vehicleGroup), [
            'group_code' => 'SERVICE_UNITS',
            'vehicle_group_description' => 'Service Units',
            'is_active' => '0',
        ])->assertRedirect(route('vehicle-groups.index'));

        $this->assertDatabaseHas('vehicle_groups', ['id' => $vehicleGroup->id, 'group_code' => 'SERVICE_UNITS', 'is_active' => false]);

        $this->actingAs($this->administrator())->delete(route('vehicle-groups.destroy', $vehicleGroup))->assertRedirect();
        $this->assertDatabaseMissing('vehicle_groups', ['id' => $vehicleGroup->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
