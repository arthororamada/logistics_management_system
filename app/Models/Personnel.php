<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Personnel extends Model { protected $table='personnel'; protected $fillable=['employee_id','last_name','first_name','middle_name','address','contact_number','position','personnel_type','warehouse_code','drivers_license','is_mechanic','is_active','remarks']; protected $casts=['is_mechanic'=>'boolean','is_active'=>'boolean']; }
