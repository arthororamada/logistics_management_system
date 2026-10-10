<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripType extends Model
{
    use HasFactory;

    protected $fillable = ['trip_type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
