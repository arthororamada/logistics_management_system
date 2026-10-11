<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaybillPackage extends Model
{
    protected $table = 'packages';

    protected $guarded = ['id'];

    protected $casts = [
        'pickup_date' => 'datetime', 'handover_date' => 'datetime',
        'delivery_min_time' => 'datetime', 'delivery_max_time' => 'datetime',
        'is_priority' => 'boolean', 'is_deleted' => 'datetime',
    ];

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }

    public function seller(): BelongsTo { return $this->belongsTo(Client::class, 'seller_id'); }

    public function primaryItem(): BelongsTo { return $this->belongsTo(Item::class, 'item_id'); }

    public function items(): HasMany { return $this->hasMany(Item::class, 'waybill_number', 'waybill_number'); }

    public function statusLogs(): HasMany { return $this->hasMany(StatusLog::class, 'waybill_number', 'waybill_number'); }
}
