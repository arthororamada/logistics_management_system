<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 30)->unique();
            $table->string('vehicle_group_description', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'vehicle_group_description']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Vehicle Groups', 'slug' => 'vehicle-groups.view', 'group' => 'Vehicle Group Management', 'description' => 'View vehicle group records.'],
            ['name' => 'Create Vehicle Groups', 'slug' => 'vehicle-groups.create', 'group' => 'Vehicle Group Management', 'description' => 'Create vehicle group records.'],
            ['name' => 'Update Vehicle Groups', 'slug' => 'vehicle-groups.update', 'group' => 'Vehicle Group Management', 'description' => 'Update vehicle group details and status.'],
            ['name' => 'Delete Vehicle Groups', 'slug' => 'vehicle-groups.delete', 'group' => 'Vehicle Group Management', 'description' => 'Delete vehicle group records.'],
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
            'vehicle-groups.view', 'vehicle-groups.create', 'vehicle-groups.update', 'vehicle-groups.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicle_groups');
    }
};
