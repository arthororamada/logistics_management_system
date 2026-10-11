<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = ['waybill_numbers', 'created_by'];

    protected $casts = ['waybill_numbers' => 'array'];
}
