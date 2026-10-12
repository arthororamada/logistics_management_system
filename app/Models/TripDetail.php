<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDetail extends Model
{
    protected $fillable = ['dm_id', 'waybill_number', 'quantity', 'kilogram', 'cbm', 'drops_number', 'created_by', 'updated_by'];

    protected $casts = ['dm_id' => 'integer', 'quantity' => 'integer', 'kilogram' => 'integer', 'cbm' => 'float', 'drops_number' => 'integer'];
}
