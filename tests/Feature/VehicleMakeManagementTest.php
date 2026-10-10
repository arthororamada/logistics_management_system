<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleMake;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VehicleMakeManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_vehicle_makes(): void
    {
        $this->actingAs($this->administrator())->get(route('vehicle-makes.index'))
            ->assertOk()
            ->assertSee('Vehicle Make Directory');

        $response = $this->actingAs($this->administrator())->post(route('vehicle-makes.store'), [
            'vehicle_make' => 'Isuzu',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('vehicle-makes.index'));
        $this->assertDatabaseHas('vehicle_makes', ['vehicle_make' => 'Isuzu', 'is_active' => true]);
    }

    public function test_vehicle_make_must_be_unique(): void
    {
        $vehicleMake = 'Test Make '.str()->random(12);
        VehicleMake::create(['vehicle_make' => $vehicleMake, 'is_active' => true]);

        $this->actingAs($this->administrator())->post(route('vehicle-makes.store'), [
            'vehicle_make' => $vehicleMake,
            'is_active' => '1',
        ])->assertSessionHasErrors('vehicle_make');
    }

    public function test_administrator_can_update_and_delete_a_vehicle_make(): void
    {
        $vehicleMake = VehicleMake::create(['vehicle_make' => 'Ford', 'is_active' => true]);

        $this->actingAs($this->administrator())->put(route('vehicle-makes.update', $vehicleMake), [
            'vehicle_make' => 'Ford Commercial',
            'is_active' => '0',
        ])->assertRedirect(route('vehicle-makes.index'));

        $this->assertDatabaseHas('vehicle_makes', ['id' => $vehicleMake->id, 'vehicle_make' => 'Ford Commercial', 'is_active' => false]);

        $this->actingAs($this->administrator())->delete(route('vehicle-makes.destroy', $vehicleMake))->assertRedirect();
        $this->assertDatabaseMissing('vehicle_makes', ['id' => $vehicleMake->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
