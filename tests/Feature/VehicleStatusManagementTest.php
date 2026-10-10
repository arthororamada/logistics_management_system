<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VehicleStatusManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_vehicle_statuses(): void
    {
        $this->actingAs($this->administrator())->get(route('vehicle-statuses.index'))->assertOk()->assertSee('Vehicle Status Directory');
        $response = $this->actingAs($this->administrator())->post(route('vehicle-statuses.store'), ['vehicle_status' => 'Available', 'color' => '#16734A', 'is_active' => '1']);
        $response->assertRedirect(route('vehicle-statuses.index'));
        $this->assertDatabaseHas('vehicle_statuses', ['vehicle_status' => 'Available', 'color' => '#16734A', 'is_active' => true]);
    }

    public function test_vehicle_status_must_be_unique(): void
    {
        $name = 'Test Status '.str()->random(10);
        VehicleStatus::create(['vehicle_status' => $name, 'color' => '#0B1F3A', 'is_active' => true]);
        $this->actingAs($this->administrator())->post(route('vehicle-statuses.store'), ['vehicle_status' => $name, 'color' => '#0B1F3A', 'is_active' => '1'])->assertSessionHasErrors('vehicle_status');
    }

    public function test_administrator_can_update_and_delete_a_vehicle_status(): void
    {
        $status = VehicleStatus::create(['vehicle_status' => 'In Repair', 'color' => '#9A5B0A', 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('vehicle-statuses.update', $status), ['vehicle_status' => 'Under Repair', 'color' => '#8B1E2D', 'is_active' => '0'])->assertRedirect(route('vehicle-statuses.index'));
        $this->assertDatabaseHas('vehicle_statuses', ['id' => $status->id, 'vehicle_status' => 'Under Repair', 'color' => '#8B1E2D', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('vehicle-statuses.destroy', $status))->assertRedirect();
        $this->assertDatabaseMissing('vehicle_statuses', ['id' => $status->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
