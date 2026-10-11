<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MaintenanceManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_create_matching_vehicle_maintenance_and_status_tracks_odometer(): void
    {
        $vehicle = Vehicle::firstOrFail();
        $policy = $this->policyFor($vehicle, 'TEST_DISTANCE_POLICY');
        $baseline = (float) $vehicle->odometer;

        $this->actingAs($this->administrator())->post(route('maintenances.store'), [
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'service_date' => now()->format('Y-m-d'),
            'service_odometer' => $baseline,
            'notes' => 'Initial service',
        ])->assertRedirect();

        $maintenance = Maintenance::where('vehicle_id', $vehicle->id)->where('vehicle_maintenance_id', $policy->id)->firstOrFail();
        $maintenance->load('vehicle');
        $this->actingAs($this->administrator())->get(route('maintenances.index'))->assertOk()->assertSee('Vehicle Maintenance Directory');
        $this->actingAs($this->administrator())->get(route('maintenances.show', $maintenance))->assertOk()->assertSee('Current Maintenance Cycle');
        $this->actingAs($this->administrator())->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Maintenance Data')
            ->assertSee('TEST_DISTANCE_POLICY')
            ->assertSee('Normal');
        $this->actingAs($this->administrator())->get(route('maintenances.create', ['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('value="'.$vehicle->id.'" selected', false);
        $this->assertSame(Maintenance::STATUS_NORMAL, $maintenance->status());
        $this->assertEquals($baseline + 80, (float) $maintenance->warning_odometer);
        $this->assertEquals($baseline + 100, (float) $maintenance->next_due_odometer);
        $this->assertCount(1, $maintenance->renewals);

        $vehicle->update(['odometer' => $baseline + 80]);
        $maintenance->load('vehicle');
        $this->assertSame(Maintenance::STATUS_WARNING, $maintenance->status());

        $vehicle->update(['odometer' => $baseline + 100]);
        $maintenance->load('vehicle');
        $this->assertSame(Maintenance::STATUS_VIOLATION, $maintenance->status());
    }

    public function test_renewal_resets_threshold_and_preserves_history(): void
    {
        $vehicle = Vehicle::firstOrFail();
        $policy = $this->policyFor($vehicle, 'TEST_RENEW_POLICY');
        $baseline = (float) $vehicle->odometer;
        $administrator = $this->administrator();

        $this->actingAs($administrator)->post(route('maintenances.store'), [
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'service_date' => now()->subDay()->format('Y-m-d'),
            'service_odometer' => $baseline,
        ]);
        $maintenance = Maintenance::where('vehicle_id', $vehicle->id)->where('vehicle_maintenance_id', $policy->id)->firstOrFail();

        $renewalOdometer = $baseline + 100;
        $this->actingAs($administrator)->post(route('maintenances.renew', $maintenance), [
            'service_date' => now()->format('Y-m-d'),
            'service_odometer' => $renewalOdometer,
            'notes' => 'Completed scheduled service',
        ])->assertRedirect();

        $maintenance->refresh()->load('vehicle');
        $this->assertEquals($renewalOdometer + 100, (float) $maintenance->next_due_odometer);
        $this->assertEquals($renewalOdometer, (float) $maintenance->vehicle->odometer);
        $this->assertSame(Maintenance::STATUS_NORMAL, $maintenance->status());
        $this->assertCount(2, $maintenance->renewals);
    }

    public function test_policy_must_match_vehicle_group_and_type(): void
    {
        $vehicle = Vehicle::firstOrFail();
        $policy = VehicleMaintenance::create([
            'maintenance_code' => 'TEST_WRONG_CLASS',
            'maintenance_description' => 'Wrong vehicle classification',
            'vehicle_group' => 'Different group',
            'vehicle_type' => 'Different type',
            'maintenance_type' => 'distance_policy',
            'threshold' => 100,
            'threshold_warning' => 20,
            'is_active' => true,
            'created_by' => $this->administrator()->id,
            'update_by' => $this->administrator()->id,
        ]);

        $this->actingAs($this->administrator())->post(route('maintenances.store'), [
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'service_date' => now()->format('Y-m-d'),
            'service_odometer' => $vehicle->odometer,
        ])->assertSessionHasErrors('vehicle_maintenance_id');
    }

    public function test_same_policy_cannot_be_assigned_twice_to_one_vehicle(): void
    {
        $vehicle = Vehicle::firstOrFail();
        $policy = $this->policyFor($vehicle, 'TEST_DUPLICATE_POLICY');
        $payload = [
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'service_date' => now()->format('Y-m-d'),
            'service_odometer' => $vehicle->odometer,
        ];

        $this->actingAs($this->administrator())->post(route('maintenances.store'), $payload);
        $this->actingAs($this->administrator())->post(route('maintenances.store'), $payload)
            ->assertSessionHasErrors('vehicle_maintenance_id');
    }

    public function test_date_policy_triggers_violation_from_calendar_threshold(): void
    {
        $vehicle = Vehicle::firstOrFail();
        $administrator = $this->administrator();
        $policy = VehicleMaintenance::create([
            'maintenance_code' => 'TEST_DATE_POLICY',
            'maintenance_description' => 'Test annual maintenance',
            'vehicle_group' => $vehicle->vehicle_group,
            'vehicle_type' => $vehicle->vehicle_type,
            'maintenance_type' => 'date_policy',
            'threshold' => 10,
            'threshold_warning' => 2,
            'is_active' => true,
            'created_by' => $administrator->id,
            'update_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)->post(route('maintenances.store'), [
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'service_date' => now()->subDays(10)->format('Y-m-d'),
            'service_odometer' => $vehicle->odometer,
        ]);

        $maintenance = Maintenance::where('vehicle_maintenance_id', $policy->id)->firstOrFail()->load('vehicle');
        $this->assertSame(Maintenance::STATUS_VIOLATION, $maintenance->status());
        $this->assertTrue($maintenance->next_due_date->isToday());
    }

    private function policyFor(Vehicle $vehicle, string $code): VehicleMaintenance
    {
        return VehicleMaintenance::create([
            'maintenance_code' => $code,
            'maintenance_description' => 'Test distance maintenance',
            'vehicle_group' => $vehicle->vehicle_group,
            'vehicle_type' => $vehicle->vehicle_type,
            'maintenance_type' => 'distance_policy',
            'threshold' => 100,
            'threshold_warning' => 20,
            'is_active' => true,
            'created_by' => $this->administrator()->id,
            'update_by' => $this->administrator()->id,
        ]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
