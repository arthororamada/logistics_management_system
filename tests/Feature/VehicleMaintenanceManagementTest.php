<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleGroup;
use App\Models\VehicleMaintenance;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleMaintenanceManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_create_update_and_delete_a_maintenance_policy(): void
    {
        [$group, $type] = $this->configuration();
        $administrator = $this->administrator();

        $this->actingAs($administrator)->get(route('vehicle-maintenances.index'))
            ->assertOk()
            ->assertSee('Vehicle Maintenance Directory');

        $this->actingAs($administrator)->post(route('vehicle-maintenances.store'), [
            'maintenance_code' => 'oil_change',
            'maintenance_description' => 'Engine oil and filter change',
            'vehicle_group' => $group->vehicle_group_description,
            'vehicle_type' => $type->vehicle_type,
            'maintenance_type' => 'distance_policy',
            'threshold' => 10000,
            'threshold_warning' => 1000,
            'is_active' => '1',
        ])->assertRedirect(route('vehicle-maintenances.index'));

        $maintenance = VehicleMaintenance::where('maintenance_code', 'OIL_CHANGE')->firstOrFail();
        $this->assertSame($administrator->id, $maintenance->created_by);
        $this->assertSame($administrator->id, $maintenance->update_by);

        $this->actingAs($administrator)->put(route('vehicle-maintenances.update', $maintenance), [
            'maintenance_code' => 'OIL_CHANGE',
            'maintenance_description' => 'Full oil service',
            'vehicle_group' => $group->vehicle_group_description,
            'vehicle_type' => $type->vehicle_type,
            'maintenance_type' => 'date_policy',
            'threshold' => 180,
            'threshold_warning' => 30,
            'is_active' => '0',
        ])->assertRedirect(route('vehicle-maintenances.index'));

        $this->assertDatabaseHas('vehicle_maintenances', [
            'id' => $maintenance->id,
            'maintenance_description' => 'Full oil service',
            'maintenance_type' => 'date_policy',
            'is_active' => false,
        ]);

        $this->actingAs($administrator)->delete(route('vehicle-maintenances.destroy', $maintenance))->assertRedirect();
        $this->assertDatabaseMissing('vehicle_maintenances', ['id' => $maintenance->id]);
    }

    public function test_warning_threshold_cannot_exceed_the_policy_threshold(): void
    {
        [$group, $type] = $this->configuration();

        $this->actingAs($this->administrator())->post(route('vehicle-maintenances.store'), [
            'maintenance_code' => 'BRAKE_CHECK',
            'maintenance_description' => 'Brake inspection',
            'vehicle_group' => $group->vehicle_group_description,
            'vehicle_type' => $type->vehicle_type,
            'maintenance_type' => 'distance_policy',
            'threshold' => 1000,
            'threshold_warning' => 2000,
            'is_active' => '1',
        ])->assertSessionHasErrors('threshold_warning');
    }

    public function test_mass_upload_creates_and_updates_policies_by_code(): void
    {
        [$group, $type] = $this->configuration();
        $administrator = $this->administrator();
        VehicleMaintenance::create([
            'maintenance_code' => 'OIL_CHANGE',
            'maintenance_description' => 'Old description',
            'vehicle_group' => $group->vehicle_group_description,
            'vehicle_type' => $type->vehicle_type,
            'maintenance_type' => 'distance_policy',
            'threshold' => 5000,
            'threshold_warning' => 500,
            'is_active' => true,
            'created_by' => $administrator->id,
            'update_by' => $administrator->id,
        ]);

        $csv = implode("\n", [
            'maintenance_code,maintenance_description,vehicle_group,vehicle_type,maintenance_type,threshold,threshold_warning,is_active',
            'OIL_CHANGE,Updated oil service,'.$group->vehicle_group_description.','.$type->vehicle_type.',distance_policy,10000,1000,1',
            'ANNUAL_INSPECTION,Annual safety inspection,'.$group->vehicle_group_description.','.$type->vehicle_type.',date_policy,365,30,yes',
        ]);
        $file = UploadedFile::fake()->createWithContent('maintenance.csv', $csv);

        $this->actingAs($administrator)->post(route('vehicle-maintenances.import'), ['file' => $file])
            ->assertRedirect(route('vehicle-maintenances.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_maintenances', ['maintenance_code' => 'OIL_CHANGE', 'threshold' => 10000, 'maintenance_description' => 'Updated oil service']);
        $this->assertDatabaseHas('vehicle_maintenances', ['maintenance_code' => 'ANNUAL_INSPECTION', 'maintenance_type' => 'date_policy', 'threshold' => 365]);
    }

    public function test_invalid_mass_upload_is_atomic(): void
    {
        [$group, $type] = $this->configuration();
        $csv = implode("\n", [
            'maintenance_code,maintenance_description,vehicle_group,vehicle_type,maintenance_type,threshold,threshold_warning,is_active',
            'VALID_ROW,Valid policy,'.$group->vehicle_group_description.','.$type->vehicle_type.',distance_policy,10000,1000,1',
            'INVALID_ROW,Invalid policy,'.$group->vehicle_group_description.','.$type->vehicle_type.',unknown_policy,100,10,1',
        ]);

        $this->actingAs($this->administrator())->post(route('vehicle-maintenances.import'), [
            'file' => UploadedFile::fake()->createWithContent('maintenance.csv', $csv),
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('vehicle_maintenances', ['maintenance_code' => 'VALID_ROW']);
    }

    private function configuration(): array
    {
        return [
            VehicleGroup::firstOrCreate(['group_code' => 'MAINT_TEST'], ['vehicle_group_description' => 'Maintenance Test Group', 'is_active' => true]),
            VehicleType::firstOrCreate(['vehicle_type' => 'Maintenance Test Type'], ['is_active' => true]),
        ];
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}
