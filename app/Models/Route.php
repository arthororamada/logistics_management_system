<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['province', 'city', 'brgy', 'longitued', 'latitude', 'is_active'];

    protected $casts = [
        'longitued' => 'decimal:7',
        'latitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];
}
