<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Services\MaintenanceAlertService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_warning_and_violation_maintenance_are_displayed_as_notifications(): void
    {
        $warning = $this->createAlert('NOTIFY_WARNING', Maintenance::STATUS_WARNING);
        $violation = $this->createAlert('NOTIFY_VIOLATION', Maintenance::STATUS_VIOLATION);

        $alerts = app(MaintenanceAlertService::class)->all();
        $this->assertTrue($alerts->contains('id', $warning->id));
        $this->assertTrue($alerts->contains('id', $violation->id));
        $this->assertSame($violation->id, $alerts->firstWhere('policy.maintenance_code', 'NOTIFY_VIOLATION')->id);

        $this->actingAs($this->administrator())->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Maintenance Notifications')
            ->assertSee('NOTIFY_WARNING')
            ->assertSee('NOTIFY_VIOLATION')
            ->assertSee(route('maintenances.show', $warning), false)
            ->assertSee(route('maintenances.show', $violation), false);
    }

    public function test_topbar_shows_live_maintenance_alerts(): void
    {
        $alert = $this->createAlert('TOPBAR_VIOLATION', Maintenance::STATUS_VIOLATION);

        $this->actingAs($this->administrator())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Maintenance Alerts')
            ->assertSee('TOPBAR_VIOLATION')
            ->assertSee(route('maintenances.show', $alert), false);
    }

    public function test_renewing_maintenance_clears_its_notification(): void
    {
        $maintenance = $this->createAlert('CLEAR_AFTER_RENEWAL', Maintenance::STATUS_VIOLATION);
        $maintenance->load('vehicle');

        $this->actingAs($this->administrator())->post(route('maintenances.renew', $maintenance), [
            'service_date' => now()->format('Y-m-d'),
            'service_odometer' => $maintenance->vehicle->odometer,
            'notes' => 'Completed from notification alert',
        ])->assertRedirect();

        $this->assertFalse(app(MaintenanceAlertService::class)->all()->contains('id', $maintenance->id));
        $this->actingAs($this->administrator())->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('CLEAR_AFTER_RENEWAL');
    }

    private function createAlert(string $code, string $status): Maintenance
    {
        $vehicle = Vehicle::firstOrFail();
        $userId = $this->administrator()->id;
        $policy = VehicleMaintenance::create([
            'maintenance_code' => $code,
            'maintenance_description' => 'Notification test policy',
            'vehicle_group' => $vehicle->vehicle_group,
            'vehicle_type' => $vehicle->vehicle_type,
            'maintenance_type' => VehicleMaintenance::DISTANCE_POLICY,
            'threshold' => 100,
            'threshold_warning' => 20,
            'is_active' => true,
            'created_by' => $userId,
            'update_by' => $userId,
        ]);
        $odometer = (float) $vehicle->odometer;

        return Maintenance::create([
            'vehicle_id' => $vehicle->id,
            'vehicle_maintenance_id' => $policy->id,
            'policy_type' => VehicleMaintenance::DISTANCE_POLICY,
            'threshold' => 100,
            'threshold_warning' => 20,
            'last_service_date' => now(),
            'last_service_odometer' => max(0, $odometer - 100),
            'warning_odometer' => $status === Maintenance::STATUS_WARNING ? $odometer : max(0, $odometer - 20),
            'next_due_odometer' => $status === Maintenance::STATUS_WARNING ? $odometer + 10 : $odometer,
            'is_active' => true,
            'created_by' => $userId,
            'update_by' => $userId,
        ]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
