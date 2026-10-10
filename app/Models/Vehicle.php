<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_code', 'vehicle_group', 'body_number', 'vehicle_type', 'plate_number', 'vehicle_make', 'year_model',
        'engine_number', 'chassi_number', 'transmission', 'fuel_type', 'fuel_capacity', 'fuel_usage_per_kilometer',
        'engine_type', 'cargo_capacity', 'battery_size', 'tire_size', 'sunsetting_year', 'date_acquired',
        'registration_number', 'mv_file_number', 'odometer', 'vehicle_status', 'remarks',
    ];

    protected $casts = [
        'year_model' => 'integer', 'sunsetting_year' => 'integer', 'date_acquired' => 'date',
        'fuel_capacity' => 'decimal:2', 'fuel_usage_per_kilometer' => 'decimal:3', 'cargo_capacity' => 'decimal:2', 'odometer' => 'decimal:2',
    ];
}
