<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TripRate extends Model { protected $fillable = ['warehouse_code','trip_type','vehicle_group','vehicle_type','trip_number','payment_type','client_code','route_origin','farthest_destination','cargo_capacity','minimum_drop','driver_rate','helper_rate','driver_cash_released_percentage','helper_cash_released_percentage','additional_driver_rate','additional_helper_rate','bypass_fuel_liters','is_interisland','is_with_tollfee','is_bypass_fuel_usage','is_active','created_by','updated_by']; protected $casts = ['bypass_fuel_liters'=>'decimal:2','is_interisland'=>'boolean','is_with_tollfee'=>'boolean','is_bypass_fuel_usage'=>'boolean','is_active'=>'boolean']; }
