<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = ['waybill_numbers', 'created_by', 'driver_id', 'warehouse_destination'];

    protected $casts = ['waybill_numbers' => 'array'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_destination', 'warehouse_code');
    }
}
