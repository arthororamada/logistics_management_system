<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use HasFactory;

    public const STATUS_NORMAL = 'normal';
    public const STATUS_WARNING = 'warning';
    public const STATUS_VIOLATION = 'violation';

    protected $fillable = [
        'vehicle_id', 'vehicle_maintenance_id', 'policy_type', 'threshold', 'threshold_warning',
        'last_service_date', 'last_service_odometer', 'warning_date', 'next_due_date',
        'warning_odometer', 'next_due_odometer', 'is_active', 'created_by', 'update_by',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'threshold_warning' => 'integer',
        'last_service_date' => 'date',
        'last_service_odometer' => 'decimal:2',
        'warning_date' => 'date',
        'next_due_date' => 'date',
        'warning_odometer' => 'decimal:2',
        'next_due_odometer' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(VehicleMaintenance::class, 'vehicle_maintenance_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(MaintenanceRenewal::class)->latest('service_date')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'update_by');
    }

    public function status(): string
    {
        if ($this->policy_type === VehicleMaintenance::DISTANCE_POLICY) {
            $odometer = (float) ($this->vehicle?->odometer ?? 0);
            if ($odometer >= (float) $this->next_due_odometer) return self::STATUS_VIOLATION;
            if ($odometer >= (float) $this->warning_odometer) return self::STATUS_WARNING;
            return self::STATUS_NORMAL;
        }

        $today = now()->startOfDay();
        if ($this->next_due_date && $today->greaterThanOrEqualTo($this->next_due_date)) return self::STATUS_VIOLATION;
        if ($this->warning_date && $today->greaterThanOrEqualTo($this->warning_date)) return self::STATUS_WARNING;
        return self::STATUS_NORMAL;
    }

    public function remaining(): string
    {
        if ($this->policy_type === VehicleMaintenance::DISTANCE_POLICY) {
            $remaining = (float) $this->next_due_odometer - (float) ($this->vehicle?->odometer ?? 0);
            return $remaining >= 0 ? number_format($remaining, 2).' km remaining' : number_format(abs($remaining), 2).' km overdue';
        }

        $days = now()->startOfDay()->diffInDays($this->next_due_date, false);
        return $days >= 0 ? $days.' day(s) remaining' : abs($days).' day(s) overdue';
    }
}
