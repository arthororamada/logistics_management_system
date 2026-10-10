<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\User;
use App\Models\VehicleGroup;
use App\Models\VehicleMake;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_a_vehicle(): void
    {
        $this->seedConfiguration();
        $response = $this->actingAs($this->administrator())->post(route('vehicles.store'), $this->payload());
        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'ABC-1234', 'vehicle_status' => 'Available']);
    }

    public function test_vehicle_directory_returns_datatables_json_with_filters(): void
    {
        $this->seedConfiguration();
        Vehicle::create($this->payload());
        $response = $this->actingAs($this->administrator())->getJson(route('vehicles.index', ['draw' => 1, 'start' => 0, 'length' => 25, 'warehouse_code' => 'WH-001', 'vehicle_group' => 'Heavy Trucks', 'vehicle_type' => 'Cargo Truck']));
        $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])->assertJsonFragment(['plate_number' => 'ABC-1234']);
    }

    public function test_vehicle_configuration_values_are_required_to_exist(): void
    {
        $response = $this->actingAs($this->administrator())->post(route('vehicles.store'), array_merge($this->payload(), [
            'warehouse_code' => 'MISSING-WH-'.str()->random(6),
            'vehicle_group' => 'Missing Group '.str()->random(6),
            'vehicle_type' => 'Missing Type '.str()->random(6),
            'vehicle_make' => 'Missing Make '.str()->random(6),
            'vehicle_status' => 'Missing Status '.str()->random(6),
        ]));
        $response->assertSessionHasErrors(['warehouse_code', 'vehicle_group', 'vehicle_type', 'vehicle_make', 'vehicle_status']);
    }

    public function test_administrator_can_update_a_vehicle(): void
    {
        $this->seedConfiguration();
        $vehicle = Vehicle::create($this->payload());
        $this->actingAs($this->administrator())->put(route('vehicles.update', $vehicle), [...$this->payload(), 'plate_number' => 'XYZ-9876', 'remarks' => 'Updated'])->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'plate_number' => 'XYZ-9876', 'remarks' => 'Updated']);
    }

    public function test_vehicle_export_and_upload_template_are_available(): void
    {
        $this->seedConfiguration();
        $this->actingAs($this->administrator())->get(route('vehicles.export'))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->administrator())->get(route('vehicles.import-template'))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_administrator_can_mass_upload_vehicles_from_csv(): void
    {
        $this->seedConfiguration();
        $headers = array_keys($this->payload());
        $csv = implode(',', $headers)."\n".implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $this->payload()))."\n";
        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csv);
        $response = $this->actingAs($this->administrator())->post(route('vehicles.import'), ['file' => $file]);
        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'ABC-1234']);
    }

    private function seedConfiguration(): void
    {
        Warehouse::firstOrCreate(['warehouse_code' => 'WH-001'], ['warehouse_description' => 'Main Warehouse', 'longitude' => 120, 'latitude' => 14, 'complete_address' => 'Address', 'city' => 'Manila', 'province' => 'Metro Manila', 'country' => 'Philippines', 'is_active' => true]);
        VehicleGroup::firstOrCreate(['group_code' => 'TRUCKS'], ['vehicle_group_description' => 'Heavy Trucks', 'is_active' => true]);
        VehicleType::firstOrCreate(['vehicle_type' => 'Cargo Truck'], ['is_active' => true]);
        VehicleMake::firstOrCreate(['vehicle_make' => 'Isuzu'], ['is_active' => true]);
        VehicleStatus::firstOrCreate(['vehicle_status' => 'Available'], ['color' => '#16734A', 'is_active' => true]);
    }

    private function payload(): array
    {
        return ['warehouse_code' => 'WH-001', 'vehicle_group' => 'Heavy Trucks', 'body_number' => 'BODY-001', 'vehicle_type' => 'Cargo Truck', 'plate_number' => 'ABC-1234', 'vehicle_make' => 'Isuzu', 'year_model' => 2024, 'engine_number' => 'ENG-001', 'chassi_number' => 'CHS-001', 'transmission' => 'Automatic', 'fuel_type' => 'Diesel', 'fuel_capacity' => 200, 'fuel_usage_per_kilometer' => 0.25, 'engine_type' => 'Diesel', 'cargo_capacity' => 5000, 'battery_size' => '12V', 'tire_size' => '295/80R22.5', 'sunsetting_year' => 2039, 'date_acquired' => '2024-01-15', 'registration_number' => 'REG-001', 'mv_file_number' => 'MV-001', 'odometer' => 1000, 'vehicle_status' => 'Available', 'remarks' => 'Test vehicle'];
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}
