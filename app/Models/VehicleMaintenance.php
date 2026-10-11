<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMaintenance extends Model
{
    use HasFactory;

    public const DISTANCE_POLICY = 'distance_policy';
    public const DATE_POLICY = 'date_policy';

    protected $fillable = [
        'maintenance_code',
        'maintenance_description',
        'vehicle_group',
        'vehicle_type',
        'maintenance_type',
        'threshold',
        'threshold_warning',
        'is_active',
        'created_by',
        'update_by',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'threshold_warning' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'update_by');
    }

    public function maintenanceAssignments(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
