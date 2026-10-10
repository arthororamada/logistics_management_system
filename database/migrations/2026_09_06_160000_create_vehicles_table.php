<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code', 30);
            $table->string('vehicle_group', 100);
            $table->string('body_number', 100);
            $table->string('vehicle_type', 100);
            $table->string('plate_number', 30)->unique();
            $table->string('vehicle_make', 100);
            $table->unsignedSmallInteger('year_model');
            $table->string('engine_number', 100);
            $table->string('chassi_number', 100);
            $table->string('transmission', 50);
            $table->string('fuel_type', 50);
            $table->decimal('fuel_capacity', 10, 2);
            $table->decimal('fuel_usage_per_kilometer', 10, 3);
            $table->string('engine_type', 100);
            $table->decimal('cargo_capacity', 12, 2);
            $table->string('battery_size', 50)->nullable();
            $table->string('tire_size', 50)->nullable();
            $table->unsignedSmallInteger('sunsetting_year')->nullable();
            $table->date('date_acquired')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('mv_file_number', 100)->nullable();
            $table->decimal('odometer', 12, 2)->default(0);
            $table->string('vehicle_status', 100);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['warehouse_code', 'vehicle_group']);
            $table->index(['vehicle_type', 'vehicle_make']);
            $table->index(['vehicle_status', 'plate_number']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Vehicles', 'slug' => 'vehicles.view', 'group' => 'Vehicle Management', 'description' => 'View vehicle records.'],
            ['name' => 'Create Vehicles', 'slug' => 'vehicles.create', 'group' => 'Vehicle Management', 'description' => 'Create vehicle records.'],
            ['name' => 'Update Vehicles', 'slug' => 'vehicles.update', 'group' => 'Vehicle Management', 'description' => 'Update vehicle details.'],
        ];
        foreach ($permissions as &$permission) { $permission['created_at'] = $now; $permission['updated_at'] = $now; }
        unset($permission);
        DB::table('permissions')->insert($permissions);
        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $administratorRoleId])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['vehicles.view', 'vehicles.create', 'vehicles.update'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicles');
    }
};
