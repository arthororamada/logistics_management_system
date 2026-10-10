<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_make', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'vehicle_make']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Vehicle Makes', 'slug' => 'vehicle-makes.view', 'group' => 'Vehicle Make Management', 'description' => 'View vehicle make records.'],
            ['name' => 'Create Vehicle Makes', 'slug' => 'vehicle-makes.create', 'group' => 'Vehicle Make Management', 'description' => 'Create vehicle make records.'],
            ['name' => 'Update Vehicle Makes', 'slug' => 'vehicle-makes.update', 'group' => 'Vehicle Make Management', 'description' => 'Update vehicle make details and status.'],
            ['name' => 'Delete Vehicle Makes', 'slug' => 'vehicle-makes.delete', 'group' => 'Vehicle Make Management', 'description' => 'Delete vehicle make records.'],
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
            'vehicle-makes.view', 'vehicle-makes.create', 'vehicle-makes.update', 'vehicle-makes.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicle_makes');
    }
};
