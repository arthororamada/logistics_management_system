<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        $now = now();
        $permissionRows = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group' => 'Dashboard', 'description' => 'Access the fleet operations dashboard.'],
            ['name' => 'View Template', 'slug' => 'template.view', 'group' => 'General', 'description' => 'Access the reusable template page.'],
            ['name' => 'View Roles', 'slug' => 'roles.view', 'group' => 'Access Control', 'description' => 'View roles and assigned permissions.'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'group' => 'Access Control', 'description' => 'Create new application roles.'],
            ['name' => 'Update Roles', 'slug' => 'roles.update', 'group' => 'Access Control', 'description' => 'Edit roles and permission assignments.'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'group' => 'Access Control', 'description' => 'Delete non-system roles.'],
            ['name' => 'View Permissions', 'slug' => 'permissions.view', 'group' => 'Access Control', 'description' => 'View the application permission registry.'],
            ['name' => 'Assign User Roles', 'slug' => 'users.roles.manage', 'group' => 'Access Control', 'description' => 'Assign roles to user accounts.'],
        ];

        foreach ($permissionRows as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);
        DB::table('permissions')->insert($permissionRows);

        $administratorRoleId = DB::table('roles')->insertGetId([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'description' => 'Full access to all system features and settings.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $staffRoleId = DB::table('roles')->insertGetId([
            'name' => 'Staff',
            'slug' => 'staff',
            'description' => 'Basic access for fleet operations staff.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionIds = DB::table('permissions')->pluck('id');
        DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $administratorRoleId,
        ])->all());

        $staffPermissionIds = DB::table('permissions')
            ->whereIn('slug', ['dashboard.view', 'template.view'])
            ->pluck('id');
        DB::table('permission_role')->insert($staffPermissionIds->map(fn ($permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $staffRoleId,
        ])->all());

        $adminUserId = DB::table('users')->where('username', 'admin')->value('id');
        if ($adminUserId) {
            DB::table('role_user')->insert([
                'role_id' => $administratorRoleId,
                'user_id' => $adminUserId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
