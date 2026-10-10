<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        $now = now();
        $permissions = [
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'User Management', 'description' => 'View application user accounts.'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'User Management', 'description' => 'Create authenticated user accounts.'],
            ['name' => 'Update Users', 'slug' => 'users.update', 'group' => 'User Management', 'description' => 'Update user details, status, passwords, and roles.'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'User Management', 'description' => 'Delete user accounts.'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);
        DB::table('permissions')->insert($permissions);

        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
        if ($administratorRoleId) {
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'users.view', 'users.create', 'users.update', 'users.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('administrator')->after('username');
        });
    }
};
