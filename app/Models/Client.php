<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_code',
        'client_name',
        'address',
        'focal_person',
        'contact_number',
        'api_key',
        'token',
        'endpoint',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
