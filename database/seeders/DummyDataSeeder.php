<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = User::where('username', 'admin')->firstOrFail()->id;
            $this->configuration($admin);
            $this->fleet($admin);
            $this->waybills($admin);
            $this->maintenance($admin);
            $this->trips($admin);
        });

        foreach (['clients', 'vehicles', 'personnel', 'packages', 'items', 'trips', 'maintenances', 'pickups', 'inbounds', 'transfers', 'deliveries', 'returns'] as $table) {
            $this->command?->info($table.': '.DB::table($table)->count().' records');
        }
    }

    // Existing records, including samples edited through the application, are preserved.
    private function record(string $table, array $key, array $values): int
    {
        $existing = DB::table($table)->where($key)->value('id');
        return $existing ?? DB::table($table)->insertGetId($key + $values + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function configuration(int $admin): void
    {
        foreach ([['MNL', 'Metro Manila', 'Quezon City', 121.0437, 14.6760], ['CEB', 'Cebu', 'Cebu City', 123.8854, 10.3157], ['DVO', 'Davao', 'Davao City', 125.6128, 7.0731]] as [$code, $province, $city, $long, $lat]) {
            $this->record('warehouses', ['warehouse_code' => 'DEMO-'.$code], ['warehouse_description' => $city.' Demo Warehouse', 'longitude' => $long, 'latitude' => $lat, 'complete_address' => '100 Sample Logistics Avenue, '.$city, 'city' => $city, 'province' => $province, 'country' => 'Philippines', 'is_active' => true]);
            $this->record('locations', ['location_code' => 'DEMO-'.$code], ['location_name' => $city.' Demo Hub', 'location_long' => $long, 'location_lat' => $lat, 'created_by' => $admin]);
            $this->record('routes', ['province' => $province, 'city' => $city, 'brgy' => 'Demo Central'], ['longitued' => $long, 'latitude' => $lat, 'is_active' => true]);
        }
        foreach (['SMALL_TRUCKS' => 'Small Trucks', 'BIG_TRUCKS' => 'Big Trucks'] as $code => $description) {
            $this->record('vehicle_groups', ['group_code' => $code], ['vehicle_group_description' => $description, 'is_active' => true]);
        }
        foreach (['vehicle_types' => ['vehicle_type', ['4W', '6W', '10W/32-FOOTER', '10W/64-FOOTER']], 'vehicle_makes' => ['vehicle_make', ['Isuzu', 'Hino', 'Fuso']], 'personnel_types' => ['personnel_type', ['Driver', 'Helper', 'Mechanic']], 'trip_types' => ['trip_type', ['Local Delivery', 'Interisland']], 'trip_payments' => ['payment_type', ['Per Trip', 'Per Drop']]] as $table => [$field, $values]) {
            foreach ($values as $value) $this->record($table, [$field => $value], ['is_active' => true]);
        }
        foreach (['Running' => '#22c55e', 'Under Maintenance' => '#f59e0b', 'Inactive' => '#6b7280'] as $status => $color) {
            $this->record('vehicle_statuses', ['vehicle_status' => $status], ['color' => $color, 'is_active' => true]);
        }
        foreach (['TOLL_FEE' => ['Toll fees', true], 'PARKING' => ['Parking fees', true], 'FUEL_PURCHASE' => ['Supplier fuel', false], 'CASH_FUEL' => ['Cash fuel', true], 'RORO_COST' => ['Ferry crossing', false]] as $code => [$name, $cash]) {
            $this->record('other_costs', ['cost_code' => $code], ['cost_name' => $name, 'is_cash_release' => $cash, 'is_active' => true]);
        }
        foreach (['Demo Metro Fuel', 'Demo Cebu Fuel', 'Demo Davao Fuel'] as $index => $name) {
            $this->record('fuel_suppliers', ['fuel_supplier_name' => $name], ['price_per_liter' => 58 + $index, 'is_active' => true]);
        }
        foreach (['Demo Island Ferries', 'Demo Coast Shipping'] as $name) {
            $this->record('shipping_lines', ['shipping_line_name' => $name], ['billing_address' => '100 Sample Port Road', 'focal_person' => 'Demo Shipping Coordinator', 'contact_number' => '09000000000', 'is_active' => true]);
            foreach (['4W', '6W', '10W/32-FOOTER'] as $type) {
                $this->record('shipping_rates', ['shipping_line_name' => $name, 'port_origin' => 'Manila', 'port_destination' => 'Cebu', 'vehicle_type' => $type], ['shippine_rate' => 4500, 'is_active' => true]);
            }
        }
        foreach (range(1, 5) as $i) {
            $this->record('clients', ['client_code' => sprintf('DEMO-C%02d', $i)], ['client_name' => 'Demo Retail Client '.$i, 'prefix' => sprintf('DEMO-C%02d-', $i), 'last_value' => 0, 'address' => '100 Sample Retail Street, Quezon City', 'focal_person' => 'Demo Contact '.$i, 'contact_number' => '09000000000', 'is_active' => true]);
        }
        $staff = DB::table('roles')->where('slug', 'staff')->value('id');
        foreach (range(1, 3) as $i) {
            $id = $this->record('users', ['username' => 'demo.staff'.$i], ['name' => 'Demo Staff '.$i, 'location_code' => 'DEMO-MNL', 'is_active' => true, 'password' => Hash::make('DemoPass123!')]);
            if ($staff) DB::table('role_user')->insertOrIgnore(['role_id' => $staff, 'user_id' => $id]);
        }
    }

    private function fleet(int $admin): void
    {
        $firstNames = ['Miguel', 'Ana', 'Paolo', 'Maria', 'Jose', 'Liza', 'Carlo', 'Nina'];
        foreach (range(1, 16) as $i) {
            $driver = $i % 2 === 1;
            $this->record('personnel', ['employee_id' => sprintf('DEMO-EMP-%03d', $i)], ['first_name' => $firstNames[($i - 1) % 8], 'last_name' => 'Sample'.$i, 'address' => '100 Sample Residence, Quezon City', 'contact_number' => '09000000000', 'position' => $driver ? 'Driver' : 'Helper', 'personnel_type' => $driver ? 'Driver' : 'Helper', 'warehouse_code' => 'DEMO-MNL', 'drivers_license' => $driver ? 'DEMO-LICENSE-'.$i : null, 'is_mechanic' => false, 'is_active' => true, 'remarks' => 'Dummy data']);
        }
        foreach (range(1, 8) as $i) {
            $type = $i <= 4 ? '4W' : '6W';
            $this->record('vehicles', ['body_number' => sprintf('DEMO-VAN-%03d', $i)], ['warehouse_code' => 'DEMO-MNL', 'vehicle_group' => 'Small Trucks', 'vehicle_type' => $type, 'plate_number' => sprintf('DMO-%04d', $i), 'vehicle_make' => 'Isuzu', 'year_model' => 2023, 'engine_number' => 'DEMO-ENG-'.$i, 'chassi_number' => 'DEMO-CHASSIS-'.$i, 'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'fuel_capacity' => 100, 'fuel_usage_per_kilometer' => 0.25, 'engine_type' => 'Diesel Turbo', 'cargo_capacity' => 3000, 'battery_size' => 'N70', 'tire_size' => '215/75R16', 'sunsetting_year' => 2033, 'date_acquired' => '2023-01-15', 'registration_number' => 'DEMO-REG-'.$i, 'mv_file_number' => 'DEMO-MV-'.$i, 'odometer' => 30000 + 1000 * $i, 'vehicle_status' => $i === 8 ? 'Under Maintenance' : 'Running', 'remarks' => 'Dummy data']);
        }
        foreach (['4W', '6W'] as $type) {
            $this->record('trip_rates', ['trip_number' => 'DEMO-RATE-'.$type], ['warehouse_code' => 'DEMO-MNL', 'trip_type' => 'Local Delivery', 'vehicle_group' => 'Small Trucks', 'vehicle_type' => $type, 'payment_type' => 'Per Trip', 'client_code' => 'DEMO-C01', 'route_origin' => 'Quezon City', 'farthest_destination' => 'Cebu City', 'cargo_capacity' => 3000, 'minimum_drop' => 3, 'driver_rate' => 1200, 'helper_rate' => 700, 'additional_driver_rate' => 100, 'additional_helper_rate' => 50, 'bypass_fuel_liters' => 0, 'driver_cash_released_percentage' => 50, 'helper_cash_released_percentage' => 50, 'is_interisland' => false, 'is_with_tollfee' => true, 'is_bypass_fuel_usage' => false, 'is_active' => true, 'created_by' => $admin, 'updated_by' => $admin]);
        }
    }

    private function waybills(int $admin): void
    {
        $stages = ['for_pickup', 'for_pickup_dispatch', 'pickup_dispatched', 'accepted_by_courier', 'accepted_to_warehouse', 'for_transfer_dispatch', 'for_delivery_dispatch', 'delivery_dispatched', 'for_return_dispatch', 'return_dispatched'];
        foreach (range(1, 60) as $i) {
            $number = sprintf('DEMO-WB-%05d', $i);
            if (DB::table('packages')->where('waybill_number', $number)->exists()) continue;
            $client = DB::table('clients')->where('client_code', sprintf('DEMO-C%02d', ($i - 1) % 5 + 1))->value('id');
            $status = $stages[intdiv($i - 1, 6)];
            $packageId = $this->record('packages', ['waybill_number' => $number], ['client_id' => $client, 'seller_id' => $client, 'order_number' => sprintf('DEMO-ORD-%05d', $i), 'package_id' => 'PKG-'.$number, 'package_location' => 'DEMO-MNL', 'package_status' => $status, 'package_description' => 'Demo household goods '.$i, 'package_sku' => 'DEMO-SKU-'.$i, 'package_size' => 'Medium', 'package_quantity' => 2, 'package_length' => 30, 'package_width' => 20, 'package_height' => 15, 'package_weight' => 2.5, 'payment_type' => $i % 2 ? 'COD' : 'Prepaid', 'total_price' => 500 + 20 * $i, 'declared_value' => 500 + 20 * $i, 'package_remarks' => 'Dummy data', 'buyer_name' => 'Demo Buyer '.$i, 'buyer_mobile' => '09000000000', 'buyer_email' => 'buyer'.$i.'@example.com', 'buyer_full_address' => $i.' Sample Street, Quezon City', 'buyer_province' => 'Metro Manila', 'buyer_city' => 'Quezon City', 'buyer_barangay' => 'Demo Central', 'buyer_long' => 121.0437, 'buyer_lat' => 14.6760, 'pickup_date' => now()->subDays(3), 'delivery_min_time' => now()->addDay()->startOfDay(), 'delivery_max_time' => now()->addDays(2)->endOfDay(), 'is_priority' => $i % 5 === 0, 'created_by' => $admin, 'updated_by' => $admin]);
            foreach ([1, 2] as $j) {
                $item = $this->record('items', ['waybill_number' => $number, 'item_reference' => $number.'-'.$j], ['item_description' => 'Demo household item '.$j, 'item_sku' => 'DEMO-ITEM-'.$j, 'item_length' => 15, 'item_width' => 10, 'item_height' => 10, 'item_weight' => 1.25, 'created_by' => $admin, 'updated_by' => $admin]);
                if ($j === 1) DB::table('packages')->where('id', $packageId)->update(['item_id' => $item]);
            }
            $this->record('status_logs', ['waybill_number' => $number, 'status' => 'for_pickup'], ['reference' => 'Demo seed', 'comment' => 'Sample waybill created', 'created_by' => $admin]);
            if ($status !== 'for_pickup') $this->record('status_logs', ['waybill_number' => $number, 'status' => $status], ['reference' => 'Demo seed', 'comment' => 'Sample processing stage', 'created_by' => $admin]);
        }
        foreach (['pickups' => [[7, 8, 9], [13, 14, 15]], 'inbounds' => [[25, 26, 27]], 'transfers' => [[31, 32, 33]], 'deliveries' => [[37, 38, 39], [43, 44, 45]], 'returns' => [[49, 50, 51], [55, 56, 57]]] as $table => $groups) {
            foreach ($groups as $index => $group) {
                $numbers = array_map(fn ($i) => sprintf('DEMO-WB-%05d', $i), $group);
                $manifest = $this->record($table, ['waybill_numbers' => json_encode($numbers)], ['created_by' => $admin]);
                if ($index === 0 || $table === 'inbounds') continue;
                $details = ['pickups' => 'pickup_details', 'deliveries' => 'delivery_details', 'returns' => 'return_details'][$table];
                foreach ($numbers as $number) {
                    if (! DB::table($details)->where(['dm' => $manifest, 'waybill_number' => $number])->exists()) DB::table($details)->insert(['dm' => $manifest, 'waybill_number' => $number, 'status' => 'dispatched', 'created_at' => now()]);
                }
            }
        }
    }

    private function maintenance(int $admin): void
    {
        foreach (range(1, 6) as $i) {
            $vehicle = DB::table('vehicles')->where('body_number', sprintf('DEMO-VAN-%03d', $i))->first();
            $datePolicy = $i > 3;
            $policy = $this->record('vehicle_maintenances', ['maintenance_code' => 'DEMO-POLICY-'.$i], ['maintenance_description' => $datePolicy ? 'Demo annual registration' : 'Demo oil and filter change', 'vehicle_group' => $vehicle->vehicle_group, 'vehicle_type' => $vehicle->vehicle_type, 'maintenance_type' => $datePolicy ? 'date_policy' : 'distance_policy', 'threshold' => $datePolicy ? 365 : 10000, 'threshold_warning' => $datePolicy ? 30 : 1000, 'is_active' => true, 'created_by' => $admin]);
            $remaining = [5000, 500, -200][($i - 1) % 3];
            $due = now()->startOfDay()->addDays([90, 15, -10][($i - 1) % 3]);
            $baseline = $datePolicy ? $vehicle->odometer - 10000 : $vehicle->odometer + $remaining - 10000;
            $id = $this->record('maintenances', ['vehicle_id' => $vehicle->id, 'vehicle_maintenance_id' => $policy], ['policy_type' => $datePolicy ? 'date_policy' : 'distance_policy', 'threshold' => $datePolicy ? 365 : 10000, 'threshold_warning' => $datePolicy ? 30 : 1000, 'last_service_date' => $datePolicy ? $due->copy()->subDays(365)->toDateString() : now()->subMonths(3)->toDateString(), 'last_service_odometer' => $baseline, 'warning_date' => $datePolicy ? $due->copy()->subDays(30)->toDateString() : null, 'next_due_date' => $datePolicy ? $due->toDateString() : null, 'warning_odometer' => $datePolicy ? null : $baseline + 9000, 'next_due_odometer' => $datePolicy ? null : $baseline + 10000, 'is_active' => true, 'created_by' => $admin]);
            $cycle = DB::table('maintenances')->where('id', $id)->first();
            $this->record('maintenance_renewals', ['maintenance_id' => $id, 'service_date' => $cycle->last_service_date], ['service_odometer' => $cycle->last_service_odometer, 'notes' => 'Demo service history', 'created_by' => $admin]);
        }
    }

    private function trips(int $admin): void
    {
        $service = app(TripService::class);
        $origin = DB::table('routes')->where('city', 'Quezon City')->where('brgy', 'Demo Central')->value('id');
        $destination = DB::table('routes')->where('city', 'Cebu City')->where('brgy', 'Demo Central')->value('id');
        $cost = DB::table('other_costs')->where('cost_code', 'TOLL_FEE')->value('id');
        $supplier = DB::table('fuel_suppliers')->where('fuel_supplier_name', 'Demo Metro Fuel')->value('id');
        foreach (['Completed', 'Open', 'Cancelled', 'Dispatched', 'Open', 'Completed'] as $index => $status) {
            $number = $index + 1;
            $id = sprintf('DEMO-TRIP-%03d', $number);
            if (Trip::where('trip_id', $id)->exists()) continue;
            $vehicle = DB::table('vehicles')->where('body_number', sprintf('DEMO-VAN-%03d', $number))->first();
            $tariff = DB::table('trip_rates')->where('trip_number', 'DEMO-RATE-'.$vehicle->vehicle_type)->value('id');
            $trip = Trip::create(['trip_id' => $id, 'creation_key' => (string) Str::uuid(), 'current_status' => 'Open', 'created_by' => $admin, 'updated_by' => $admin]);
            $trip->history()->create(['status' => 'Open', 'reason' => 'Demo trip created', 'created_by' => $admin]);
            $people = [];
            foreach (['Driver', 'Helper'] as $offset => $designation) {
                $employee = DB::table('personnel')->where('employee_id', sprintf('DEMO-EMP-%03d', $index * 2 + $offset + 1))->value('id');
                $people[] = ['employee_id' => $employee, 'designation' => $designation];
            }
            $service->save($trip, ['version' => 0, 'vehicle_id' => $vehicle->id, 'tariff_id' => $tariff, 'trip_no' => (string) ($index % 5 + 1), 'trip_type' => 'Local Delivery', 'payment_type' => 'Per Trip', 'drops_number' => 4, 'personnels' => $people, 'segments' => [['origin_id' => $origin, 'destination_id' => $destination, 'total_distance' => 150]], 'fuel_purchases' => [['fuel_supplier' => $supplier, 'liters_required' => 30, 'price_per_liter' => 58, 'remarks' => 'Demo fuel purchase']], 'costs' => [['cost_code' => $cost, 'cost_amount' => 350, 'remarks' => 'Demo toll cost']], 'disbursements' => []], $admin);
            if (in_array($status, ['Dispatched', 'Completed'])) $service->dispatch($trip, $admin, 'Demo dispatch');
            if ($status === 'Completed') $service->complete($trip, $admin, 'Demo delivery completed');
            if ($status === 'Cancelled') $service->updateStatus($trip, 'Cancelled', 'Demo cancellation', $trip->version, $admin);
        }
    }
}
