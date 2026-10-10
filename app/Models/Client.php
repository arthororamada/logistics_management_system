<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_code',
        'client_name',
        'prefix',
        'last_value',
        'full_address',
        'province',
        'city',
        'barangay',
        'email',
        'building_type',
        'focal_person',
        'contact_number',
        'api_key',
        'token',
        'endpoint',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'last_value' => 'integer'];

    public function packages(): HasMany
    {
        return $this->hasMany(WaybillPackage::class);
    }

    public function sellerPackages(): HasMany
    {
        return $this->hasMany(WaybillPackage::class, 'seller_id');
    }
}
