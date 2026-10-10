<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonnelType extends Model { protected $fillable=['personnel_type','is_active']; protected $casts=['is_active'=>'boolean']; }
