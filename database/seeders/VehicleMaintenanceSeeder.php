<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VehicleGroup;
use App\Models\VehicleMaintenance;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $administratorId = User::where('username', 'admin')->value('id') ?? User::value('id');
        $groups = VehicleGroup::where('is_active', true)->pluck('vehicle_group_description', 'group_code');
        $types = VehicleType::where('is_active', true)->pluck('vehicle_type')->flip();

        if ($groups->isEmpty() || $types->isEmpty()) {
            $this->command?->warn('Vehicle maintenance samples were skipped because active vehicle groups and types are required.');
            return;
        }

        $smallTrucks = $groups->get('SMALL_TRUCKS', $groups->first());
        $bigTrucks = $groups->get('BIG_TRUCKS', $groups->first());
        $fourWheel = $types->has('4W') ? '4W' : $types->keys()->first();
        $sixWheel = $types->has('6W') ? '6W' : $types->keys()->first();
        $tenWheel = $types->has('10W/32-FOOTER') ? '10W/32-FOOTER' : $types->keys()->first();
        $longTruck = $types->has('10W/64-FOOTER') ? '10W/64-FOOTER' : $tenWheel;

        $samples = [
            ['ENG_OIL_CHANGE_4W', 'Engine System - Engine oil and filter change', $smallTrucks, $fourWheel, 'distance_policy', 10000, 1000],
            ['ENG_AIR_FILTER_6W', 'Engine System - Air filter inspection and replacement', $smallTrucks, $sixWheel, 'distance_policy', 20000, 2000],
            ['COOLANT_REPLACE_10W', 'Coolant System - Drain, flush, and replace engine coolant', $bigTrucks, $tenWheel, 'date_policy', 365, 30],
            ['COOLANT_HOSE_10W', 'Coolant System - Inspect radiator and coolant hoses', $bigTrucks, $longTruck, 'distance_policy', 20000, 2000],
            ['VEHICLE_REGISTRATION', 'Vehicle Registration - Annual registration renewal', $smallTrucks, $fourWheel, 'date_policy', 365, 30],
            ['TIRE_ROTATION_4W', 'Tires - Inspect and rotate tires', $smallTrucks, $fourWheel, 'distance_policy', 10000, 1000],
            ['TIRE_INSPECTION_6W', 'Tires - Tread depth, pressure, and damage inspection', $smallTrucks, $sixWheel, 'distance_policy', 5000, 500],
            ['TIRE_REPLACEMENT_10W', 'Tires - Planned tire replacement review', $bigTrucks, $tenWheel, 'distance_policy', 50000, 5000],
            ['BRAKE_INSPECTION_10W', 'Brake System - Inspect pads, drums, lines, and fluid', $bigTrucks, $longTruck, 'distance_policy', 15000, 1500],
            ['BATTERY_INSPECTION_4W', 'Electrical System - Battery and charging inspection', $smallTrucks, $fourWheel, 'date_policy', 180, 14],
            ['TRANSMISSION_SERVICE_6W', 'Transmission System - Fluid and component service', $smallTrucks, $sixWheel, 'distance_policy', 40000, 5000],
            ['SAFETY_INSPECTION_10W', 'Safety System - Comprehensive vehicle safety inspection', $bigTrucks, $tenWheel, 'date_policy', 180, 14],
        ];

        foreach ($samples as [$code, $description, $group, $type, $policy, $threshold, $warning]) {
            VehicleMaintenance::updateOrCreate(
                ['maintenance_code' => $code],
                [
                    'maintenance_description' => $description,
                    'vehicle_group' => $group,
                    'vehicle_type' => $type,
                    'maintenance_type' => $policy,
                    'threshold' => $threshold,
                    'threshold_warning' => $warning,
                    'is_active' => true,
                    'created_by' => $administratorId,
                    'update_by' => $administratorId,
                ]
            );
        }

        $this->command?->info(count($samples).' vehicle maintenance sample policies seeded.');
    }
}
