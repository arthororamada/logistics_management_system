<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = [
        'location_code',
        'location_name',
        'location_long',
        'location_lat',
        'created_by',
        'updated_by',
        'is_deleted',
    ];

    protected $casts = [
        'location_long' => 'decimal:8',
        'location_lat' => 'decimal:8',
        'is_deleted' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
