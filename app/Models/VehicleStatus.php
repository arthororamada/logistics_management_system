<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleStatus extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_status', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
