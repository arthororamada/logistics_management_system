<?php

namespace App\Services;

use App\Models\Maintenance;
use Illuminate\Support\Collection;

class MaintenanceAlertService
{
    private ?Collection $alerts = null;

    public function all(): Collection
    {
        if ($this->alerts !== null) return $this->alerts;

        return $this->alerts = Maintenance::query()
            ->select('maintenances.*')
            ->join('vehicles', 'vehicles.id', '=', 'maintenances.vehicle_id')
            ->with([
                'vehicle:id,plate_number,body_number,vehicle_group,vehicle_type,odometer',
                'policy:id,maintenance_code,maintenance_description',
            ])
            ->where('maintenances.is_active', true)
            ->where(function ($query) {
                $query->where(function ($distance) {
                    $distance->where('maintenances.policy_type', 'distance_policy')
                        ->whereColumn('vehicles.odometer', '>=', 'maintenances.warning_odometer');
                })->orWhere(function ($date) {
                    $date->where('maintenances.policy_type', 'date_policy')
                        ->whereDate('maintenances.warning_date', '<=', now()->toDateString());
                });
            })
            ->get()
            ->each(fn (Maintenance $maintenance) => $maintenance->setAttribute('current_status', $maintenance->status()))
            ->filter(fn (Maintenance $maintenance) => in_array($maintenance->current_status, [Maintenance::STATUS_WARNING, Maintenance::STATUS_VIOLATION], true))
            ->sortBy(fn (Maintenance $maintenance) => [
                $maintenance->current_status === Maintenance::STATUS_VIOLATION ? 0 : 1,
                $maintenance->policy_type === 'date_policy'
                    ? ($maintenance->next_due_date?->timestamp ?? PHP_INT_MAX)
                    : (float) $maintenance->next_due_odometer,
            ])
            ->values();
    }

    public function count(): int
    {
        return $this->all()->count();
    }

    public function counts(): array
    {
        return [
            Maintenance::STATUS_WARNING => $this->all()->where('current_status', Maintenance::STATUS_WARNING)->count(),
            Maintenance::STATUS_VIOLATION => $this->all()->where('current_status', Maintenance::STATUS_VIOLATION)->count(),
        ];
    }
}
