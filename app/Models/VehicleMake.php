<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleMake extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_make', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
