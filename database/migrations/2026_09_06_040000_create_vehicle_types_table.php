<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_type', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'vehicle_type']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Vehicle Types', 'slug' => 'vehicle-types.view', 'group' => 'Vehicle Type Management', 'description' => 'View vehicle type records.'],
            ['name' => 'Create Vehicle Types', 'slug' => 'vehicle-types.create', 'group' => 'Vehicle Type Management', 'description' => 'Create vehicle type records.'],
            ['name' => 'Update Vehicle Types', 'slug' => 'vehicle-types.update', 'group' => 'Vehicle Type Management', 'description' => 'Update vehicle type details and status.'],
            ['name' => 'Delete Vehicle Types', 'slug' => 'vehicle-types.delete', 'group' => 'Vehicle Type Management', 'description' => 'Delete vehicle type records.'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);
        DB::table('permissions')->insert($permissions);

        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'vehicle-types.view', 'vehicle-types.create', 'vehicle-types.update', 'vehicle-types.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicle_types');
    }
};
