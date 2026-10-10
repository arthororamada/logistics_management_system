<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherCost extends Model
{
    use HasFactory;

    protected $fillable = ['cost_code', 'cost_name', 'is_cash_release', 'is_active'];

    protected $casts = ['is_cash_release' => 'boolean', 'is_active' => 'boolean'];
}
