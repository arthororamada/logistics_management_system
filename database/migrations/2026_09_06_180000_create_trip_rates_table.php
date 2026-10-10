<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('trip_rates')) Schema::create('trip_rates', function (Blueprint $t) {
   $t->id();
   foreach (['warehouse_code'=>30,'trip_type'=>100,'vehicle_group'=>100,'vehicle_type'=>100,'trip_number'=>50,'payment_type'=>100,'client_code'=>50,'route_origin'=>150,'farthest_destination'=>150] as $c=>$l) $t->string($c,$l);
   foreach (['cargo_capacity','minimum_drop','driver_rate','helper_rate','additional_driver_rate','additional_helper_rate'] as $c) $t->decimal($c,12,2)->default(0);
   foreach (['driver_cash_released_percentage','helper_cash_released_percentage'] as $c) $t->decimal($c,5,2)->default(0);
   foreach (['is_interisland','is_with_tollfee','is_bypass_fuel_usage','fuel_required'] as $c) $t->boolean($c)->default(false);
   $t->timestamps(); $t->index(['warehouse_code','trip_type','vehicle_group','vehicle_type'], 'trip_rates_lookup_idx');
  });
  $now=now(); $permissions=[]; foreach(['view','create','update','delete'] as $action) $permissions[]=['name'=>ucfirst($action).' Trip Rates','slug'=>"trip-rates.$action",'group'=>'Tariffs','description'=>ucfirst($action).' trip rates.','created_at'=>$now,'updated_at'=>$now];
  DB::table('permissions')->insert($permissions); $role=DB::table('roles')->where('slug','administrator')->value('id'); if($role) DB::table('permission_role')->insert(DB::table('permissions')->whereIn('slug',collect($permissions)->pluck('slug'))->pluck('id')->map(fn($id)=>['permission_id'=>$id,'role_id'=>$role])->all());
 }
 public function down(): void { $ids=DB::table('permissions')->whereIn('slug',['trip-rates.view','trip-rates.create','trip-rates.update','trip-rates.delete'])->pluck('id'); DB::table('permission_role')->whereIn('permission_id',$ids)->delete(); DB::table('permissions')->whereIn('id',$ids)->delete(); Schema::dropIfExists('trip_rates'); }
};
