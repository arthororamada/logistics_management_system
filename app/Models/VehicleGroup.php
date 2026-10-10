<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_code',
        'vehicle_group_description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
