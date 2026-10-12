<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalytics
{
    public function data(string $month, int $year): array
    {
        $counts = Vehicle::query()->selectRaw('LOWER(vehicle_status) as status, COUNT(*) as total')
            ->groupByRaw('LOWER(vehicle_status)')->pluck('total', 'status');
        $statuses = VehicleStatus::orderBy('vehicle_status')->get()->mapWithKeys(fn ($status) => [
            strtolower($status->vehicle_status) => [
                'label' => $status->vehicle_status, 'color' => $status->color, 'total' => 0,
            ],
        ]);
        foreach ($counts as $status => $count) {
            $statuses[$status] = [
                ...($statuses[$status] ?? ['label' => ucfirst($status ?: 'Unassigned'), 'color' => '#0B1F3A']),
                'total' => (int) $count,
            ];
        }

        $alerts = app(MaintenanceAlertService::class)->all();
        $maintenanceVehicles = [];
        foreach ([Maintenance::STATUS_WARNING, Maintenance::STATUS_VIOLATION] as $status) {
            $maintenanceVehicles[$status] = $alerts->where('current_status', $status)->pluck('vehicle_id')->unique()->count();
        }

        $tripVehicleCounts = Trip::query()->whereNotNull('vehicle_id')
            ->selectRaw('current_status, COUNT(DISTINCT vehicle_id) as total')
            ->groupBy('current_status')->pluck('total', 'current_status');
        $pendingManifests = [];
        foreach (['Pickup' => 'pickups', 'Delivery' => 'deliveries', 'Transfer' => 'transfers', 'Return' => 'returns'] as $label => $table) {
            $details = strtolower($label).'_details';
            $pendingManifests[$label] = DB::table($table)->whereNotExists(function ($query) use ($table, $details) {
                $query->selectRaw('1')->from($details)->whereColumn($details.'.dm', $table.'.id');
            })->count();
        }

        $dailyStart = Carbon::createFromFormat('!Y-m', $month);
        $monthlyStart = Carbon::create($year, 1, 1)->startOfDay();

        return [
            'totalVehicles' => Vehicle::count(),
            'vehicleStatuses' => $statuses->values(),
            'maintenanceVehicles' => $maintenanceVehicles,
            'tripVehicleCounts' => $tripVehicleCounts,
            'dispatchedVehicles' => (int) ($tripVehicleCounts['Dispatched'] ?? 0),
            'pendingManifests' => $pendingManifests,
            'pendingManifestTotal' => array_sum($pendingManifests),
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'dailyTrips' => $this->completedTrips($dailyStart, $dailyStart->copy()->addMonth(), 'daily'),
            'monthlyTrips' => $this->completedTrips($monthlyStart, $monthlyStart->copy()->addYear(), 'monthly'),
        ];
    }

    private function completedTrips(Carbon $start, Carbon $end, string $period): array
    {
        $counts = Trip::query()->where('current_status', 'Completed')
            ->where('date_completed', '>=', $start)->where('date_completed', '<', $end)
            ->selectRaw('DATE(date_completed) as completion_date, COUNT(*) as total')
            ->groupByRaw('DATE(date_completed)')->pluck('total', 'completion_date');
        $buckets = [];
        foreach ($counts as $date => $count) {
            $key = $period === 'daily' ? $date : substr($date, 0, 7);
            $buckets[$key] = ($buckets[$key] ?? 0) + (int) $count;
        }

        $points = [];
        for ($date = $start->copy(); $date->lt($end); $period === 'daily' ? $date->addDay() : $date->addMonth()) {
            $key = $date->format($period === 'daily' ? 'Y-m-d' : 'Y-m');
            $points[] = [
                'label' => $date->format($period === 'daily' ? 'M j' : 'M'),
                'date' => $key,
                'total' => $buckets[$key] ?? 0,
            ];
        }

        return $points;
    }
}
