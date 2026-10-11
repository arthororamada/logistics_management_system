<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = ['waybill_numbers', 'created_by', 'driver_id'];

    protected $casts = ['waybill_numbers' => 'array'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }
}
