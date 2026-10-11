<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TripRate extends Model
{
    protected $fillable = ['warehouse_code', 'trip_type', 'vehicle_group', 'vehicle_type', 'trip_number', 'payment_type', 'client_code', 'route_origin', 'farthest_destination', 'cargo_capacity', 'minimum_drop', 'driver_rate', 'helper_rate', 'driver_cash_released_percentage', 'helper_cash_released_percentage', 'additional_driver_rate', 'additional_helper_rate', 'bypass_fuel_liters', 'is_interisland', 'is_with_tollfee', 'is_bypass_fuel_usage', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['bypass_fuel_liters' => 'decimal:2', 'is_interisland' => 'boolean', 'is_with_tollfee' => 'boolean', 'is_bypass_fuel_usage' => 'boolean', 'is_active' => 'boolean'];

    public function personnelRateDefinitions(): array
    {
        $rates = [];
        foreach ($this->getAttributes() as $field => $value) {
            if (! str_ends_with($field, '_rate') || str_starts_with($field, 'additional_') || ! is_numeric($value)) {
                continue;
            }
            $role = substr($field, 0, -5);
            $rates[Str::headline($role)] = [
                'base_cents' => (int) round((float) $value * 100),
                'additional_cents' => (int) round((float) ($this->getAttribute('additional_'.$role.'_rate') ?? 0) * 100),
                'minimum_drops' => (int) $this->minimum_drop,
            ];
        }

        return $rates;
    }

    public function personnelSalaryCents(string $designation, int $drops): ?int
    {
        $definition = $this->personnelRateForDesignation($designation);

        return $definition ? $definition['base_cents'] + max(0, $drops - $definition['minimum_drops']) * $definition['additional_cents'] : null;
    }

    public function personnelRateForDesignation(string $designation): ?array
    {
        foreach ($this->personnelRateDefinitions() as $label => $definition) {
            if (Str::slug($label, '_') === Str::slug($designation, '_')) {
                return $definition;
            }
        }

        return null;
    }

    public function personnelRatesForDesignations(iterable $designations): array
    {
        $rates = [];
        foreach ($designations as $designation) {
            $rates[$designation] = $this->personnelRateForDesignation($designation);
        }

        return $rates;
    }
}
