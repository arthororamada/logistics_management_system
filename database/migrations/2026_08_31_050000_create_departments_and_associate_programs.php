<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 30)->unique();
            $table->text('department_description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'department_code']);
        });

        Schema::create('campus_department', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'department_id']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        if (DB::table('programs')->exists()) {
            $now = now();
            $departmentId = DB::table('departments')->insertGetId([
                'department_code' => 'UNASSIGNED',
                'department_description' => 'Temporary department for programs created before department management was enabled.',
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('programs')->update(['department_id' => $departmentId]);
            $campusIds = DB::table('campuses')->pluck('id');
            if ($campusIds->isNotEmpty()) {
                DB::table('campus_department')->insert($campusIds->map(fn ($campusId) => [
                    'campus_id' => $campusId,
                    'department_id' => $departmentId,
                ])->all());
            }
        }

        DB::statement('ALTER TABLE programs MODIFY department_id BIGINT UNSIGNED NOT NULL');

        $now = now();
        $permissions = [
            ['name' => 'View Departments', 'slug' => 'departments.view', 'group' => 'Department Management', 'description' => 'View school department records.'],
            ['name' => 'Create Departments', 'slug' => 'departments.create', 'group' => 'Department Management', 'description' => 'Create departments and assign campuses.'],
            ['name' => 'Update Departments', 'slug' => 'departments.update', 'group' => 'Department Management', 'description' => 'Update departments and campus assignments.'],
            ['name' => 'Delete Departments', 'slug' => 'departments.delete', 'group' => 'Department Management', 'description' => 'Delete departments without assigned programs.'],
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
            'departments.view', 'departments.create', 'departments.update', 'departments.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('campus_department');
        Schema::dropIfExists('departments');
    }
};
