<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnManifest extends Model
{
    protected $table = 'returns';

    protected $fillable = ['waybill_numbers', 'created_by', 'status'];

    protected $attributes = ['status' => 'pending'];

    protected $casts = ['waybill_numbers' => 'array'];
}
