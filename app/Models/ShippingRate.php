<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = ['shipping_line_name', 'port_origin', 'port_destination', 'vehicle_type', 'shippine_rate', 'is_active'];

    protected $casts = ['shippine_rate' => 'decimal:2', 'is_active' => 'boolean'];
}
