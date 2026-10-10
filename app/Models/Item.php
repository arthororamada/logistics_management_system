<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_deleted' => 'boolean'];

    public function package(): BelongsTo { return $this->belongsTo(WaybillPackage::class, 'waybill_number', 'waybill_number'); }
}
