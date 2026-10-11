<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date_completed' => 'datetime', 'approved_at' => 'datetime', 'tariff_snapshot' => 'array'];

    public function tripRate(): BelongsTo
    {
        return $this->belongsTo(TripRate::class, 'tariff_id');
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(TripPersonnel::class, 'trip_id', 'trip_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TripSegment::class, 'trip_id', 'trip_id');
    }

    public function fuelPurchases(): HasMany
    {
        return $this->hasMany(TripFuelPurchase::class, 'trip_id', 'trip_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TripAdditionalCost::class, 'trip_id', 'trip_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TripStatus::class, 'trip_id', 'trip_id')->orderBy('id');
    }
}
