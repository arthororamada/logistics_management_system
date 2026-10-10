<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelSupplier extends Model
{
    use HasFactory;

    protected $fillable = ['fuel_supplier_name', 'price_per_liter', 'is_active'];

    protected $casts = ['price_per_liter' => 'decimal:2', 'is_active' => 'boolean'];
}
