<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\OtherCost;
use App\Models\Route;
use App\Models\Trip;
use App\Models\TripRate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TripAssignmentConcurrencyTest extends TestCase
{
    // Separate connections need committed fixtures; remove only these fixtures in finally.
    public function test_simultaneous_dispatches_allow_only_one_owner_of_shared_resources(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This integration test requires MySQL row locking.');
        }
        foreach (['vehicle', 'personnel', 'both'] as $shared) {
            $this->assertConcurrentDispatch($shared);
        }
    }

    private function assertConcurrentDispatch(string $shared): void
    {
        $records = [];
        $processes = [];
        $readyFiles = [];
        $trips = [];
        $userId = User::where('username', 'admin')->firstOrFail()->id;
        $suffix = Str::random(10);
        try {
            $rate = TripRate::create(['warehouse_code' => 'TEST', 'trip_type' => 'Test', 'vehicle_group' => 'Truck', 'vehicle_type' => 'Truck', 'trip_number' => 'CONCURRENT-'.$suffix, 'payment_type' => 'Cash', 'client_code' => 'TEST', 'route_origin' => 'Origin', 'farthest_destination' => 'Destination', 'is_active' => true]);
            $records[] = $rate;
            $route = Route::create(['province' => 'Test', 'city' => 'Test', 'brgy' => $suffix, 'longitued' => 120, 'latitude' => 14, 'is_active' => true]);
            $records[] = $route;
            $cost = OtherCost::create(['cost_code' => 'CONCURRENT-'.$suffix, 'cost_name' => 'Concurrent test cost', 'is_cash_release' => false, 'is_active' => true]);
            $records[] = $cost;
            for ($i = 0; $i < 2; $i++) {
                $vehicle = $i && $shared !== 'personnel' ? $trips[0]->vehicle_id : null;
                if (! $vehicle) {
                    $record = Vehicle::create(['warehouse_code' => 'TEST', 'vehicle_group' => 'Truck', 'body_number' => "CONCURRENT-$suffix-$i", 'vehicle_type' => 'Truck', 'plate_number' => "CONCURRENT-$suffix-$i", 'vehicle_make' => 'Isuzu', 'year_model' => 2024, 'engine_number' => 'ENG', 'chassi_number' => 'CHS', 'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'engine_type' => 'Diesel', 'fuel_capacity' => 100, 'fuel_usage_per_kilometer' => 0.25, 'cargo_capacity' => 5000, 'vehicle_status' => 'Running']);
                    $records[] = $record;
                    $vehicle = $record->id;
                }
                $employee = $i && $shared !== 'vehicle' ? $trips[0]->personnels()->first()->employee_id : null;
                if (! $employee) {
                    $record = Personnel::create(['employee_id' => "CONCURRENT-$suffix-$i", 'first_name' => 'Concurrent', 'last_name' => 'Driver', 'address' => 'Test', 'contact_number' => '123', 'position' => 'Driver', 'personnel_type' => 'Driver', 'warehouse_code' => 'TEST', 'is_active' => true]);
                    $records[] = $record;
                    $employee = $record->id;
                }
                $trip = Trip::create(['trip_id' => "CONCURRENT-$suffix-$i", 'creation_key' => (string) Str::uuid(), 'vehicle_id' => $vehicle, 'tariff_id' => $rate->id, 'trip_no' => '1', 'trip_type' => 'Test', 'payment_type' => 'Cash', 'trip_route_origin' => 'Origin', 'trip_route_destination' => 'Destination', 'total_distance' => 1, 'current_status' => 'Open', 'created_by' => $userId, 'updated_by' => $userId]);
                $trips[] = $trip;
                $trip->personnels()->create(['employee_id' => $employee, 'designation' => 'Driver', 'created_by' => $userId]);
                $trip->costs()->create(['cost_code' => $cost->id, 'released_amount' => 0, 'nonreleased_amount' => 0, 'created_by' => $userId]);
                $trip->segments()->create(['origin_id' => $route->id, 'destination_id' => $route->id, 'origin' => 'Origin', 'destination' => 'Destination', 'total_distance' => 1, 'created_by' => $userId]);
            }
            // Both workers start while the shared resource is locked by this connection.
            DB::beginTransaction();
            if ($shared === 'personnel') {
                Personnel::whereKey($trips[0]->personnels()->first()->employee_id)->lockForUpdate()->firstOrFail();
            } else {
                Vehicle::whereKey($trips[0]->vehicle_id)->lockForUpdate()->firstOrFail();
            }
            foreach ($trips as $trip) {
                $readyFiles[] = $ready = tempnam(sys_get_temp_dir(), 'trip-race-');
                $process = new Process([PHP_BINARY, base_path('tests/Support/dispatch-trip.php'), (string) $trip->id, $ready], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (array_filter($readyFiles, fn ($file) => file_get_contents($file) !== 'ready') && microtime(true) < $deadline) {
                usleep(20000);
            }
            foreach ($readyFiles as $file) {
                $this->assertEquals('ready', file_get_contents($file), 'Worker did not reach the dispatch barrier.');
            }
            usleep(200000);
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = $process->getOutput();
            }
            $this->assertCount(1, array_filter($results, fn ($result) => $result === 'dispatched'));
            $this->assertCount(1, array_filter($results, fn ($result) => str_starts_with($result, 'rejected:') && str_contains($result, 'already assigned')));
            $this->assertEquals(1, Trip::whereIn('id', array_map(fn ($trip) => $trip->id, $trips))->where('current_status', 'Dispatched')->count());
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) $process->stop();
            }
            foreach ($trips as $trip) $trip->delete();
            foreach (array_reverse($records) as $record) $record->delete();
            foreach ($readyFiles as $file) if (is_file($file)) unlink($file);
        }
    }
}
