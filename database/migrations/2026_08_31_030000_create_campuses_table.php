<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('campus_code', 30)->unique();
            $table->string('campus_name', 150);
            $table->text('campus_address');
            $table->string('campus_color', 7)->default('#0B1F3A');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'campus_name']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Campuses', 'slug' => 'campuses.view', 'group' => 'Campus Management', 'description' => 'View campus records.'],
            ['name' => 'Create Campuses', 'slug' => 'campuses.create', 'group' => 'Campus Management', 'description' => 'Create campus records.'],
            ['name' => 'Update Campuses', 'slug' => 'campuses.update', 'group' => 'Campus Management', 'description' => 'Update campus details and status.'],
            ['name' => 'Delete Campuses', 'slug' => 'campuses.delete', 'group' => 'Campus Management', 'description' => 'Delete campus records.'],
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
            'campuses.view', 'campuses.create', 'campuses.update', 'campuses.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('campuses');
    }
};
