<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingLine extends Model
{
    use HasFactory;

    protected $fillable = ['shipping_line_name', 'billing_address', 'focal_person', 'contact_number', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
