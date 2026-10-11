<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRenewal extends Model
{
    use HasFactory;

    protected $fillable = ['maintenance_id', 'service_date', 'service_odometer', 'notes', 'created_by'];

    protected $casts = ['service_date' => 'date', 'service_odometer' => 'decimal:2'];

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
