<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REMOVED_PERMISSIONS = [
        'users.roles.manage',
        'campuses.view', 'campuses.create', 'campuses.update', 'campuses.delete',
        'programs.view', 'programs.create', 'programs.update', 'programs.delete',
        'departments.view', 'departments.create', 'departments.update', 'departments.delete',
        'terms.view', 'terms.create', 'terms.update', 'terms.delete',
        'semesters.view', 'semesters.create', 'semesters.update', 'semesters.delete',
    ];

    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('slug', self::REMOVED_PERMISSIONS)->delete();
        }

        Schema::dropIfExists('term_setups');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('campus_program');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('campus_department');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('campuses');
    }

    public function down(): void
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

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 30)->unique();
            $table->text('department_description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'department_code']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('program_code', 30)->unique();
            $table->text('program_description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'program_code']);
        });

        Schema::create('campus_department', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'department_id']);
        });

        Schema::create('campus_program', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'program_id']);
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('semester', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('term_setups', function (Blueprint $table) {
            $table->id();
            $table->string('term_year', 9);
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();
            $table->date('enrollment_period_from');
            $table->date('enrollment_period_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['term_year', 'semester_id', 'school_department_id', 'campus_id'], 'term_setup_scope_unique');
            $table->index(['is_active', 'term_year']);
        });

        $now = now();
        DB::table('semesters')->insert([
            ['semester' => 'First Semester', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['semester' => 'Second Semester', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['semester' => 'Summer Term', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissions = [
            ['name' => 'Assign User Roles', 'slug' => 'users.roles.manage', 'group' => 'Access Control', 'description' => 'Assign roles to user accounts.'],
            ['name' => 'View Campuses', 'slug' => 'campuses.view', 'group' => 'Campus Management', 'description' => 'View campus records.'],
            ['name' => 'Create Campuses', 'slug' => 'campuses.create', 'group' => 'Campus Management', 'description' => 'Create campus records.'],
            ['name' => 'Update Campuses', 'slug' => 'campuses.update', 'group' => 'Campus Management', 'description' => 'Update campus details and status.'],
            ['name' => 'Delete Campuses', 'slug' => 'campuses.delete', 'group' => 'Campus Management', 'description' => 'Delete campus records.'],
            ['name' => 'View Programs', 'slug' => 'programs.view', 'group' => 'Program Management', 'description' => 'View academic program records.'],
            ['name' => 'Create Programs', 'slug' => 'programs.create', 'group' => 'Program Management', 'description' => 'Create programs and assign campuses.'],
            ['name' => 'Update Programs', 'slug' => 'programs.update', 'group' => 'Program Management', 'description' => 'Update programs and campus assignments.'],
            ['name' => 'Delete Programs', 'slug' => 'programs.delete', 'group' => 'Program Management', 'description' => 'Delete program records.'],
            ['name' => 'View Departments', 'slug' => 'departments.view', 'group' => 'Department Management', 'description' => 'View school department records.'],
            ['name' => 'Create Departments', 'slug' => 'departments.create', 'group' => 'Department Management', 'description' => 'Create departments and assign campuses.'],
            ['name' => 'Update Departments', 'slug' => 'departments.update', 'group' => 'Department Management', 'description' => 'Update departments and campus assignments.'],
            ['name' => 'Delete Departments', 'slug' => 'departments.delete', 'group' => 'Department Management', 'description' => 'Delete departments without assigned programs.'],
            ['name' => 'View Term Setup', 'slug' => 'terms.view', 'group' => 'Configuration', 'description' => 'View configured academic terms.'],
            ['name' => 'Create Term Setup', 'slug' => 'terms.create', 'group' => 'Configuration', 'description' => 'Create academic term configurations.'],
            ['name' => 'Update Term Setup', 'slug' => 'terms.update', 'group' => 'Configuration', 'description' => 'Update term dates and configuration.'],
            ['name' => 'Delete Term Setup', 'slug' => 'terms.delete', 'group' => 'Configuration', 'description' => 'Delete academic term configurations.'],
            ['name' => 'View Semesters', 'slug' => 'semesters.view', 'group' => 'Configuration', 'description' => 'View semester options.'],
            ['name' => 'Create Semesters', 'slug' => 'semesters.create', 'group' => 'Configuration', 'description' => 'Create semester options.'],
            ['name' => 'Update Semesters', 'slug' => 'semesters.update', 'group' => 'Configuration', 'description' => 'Update semester options and status.'],
            ['name' => 'Delete Semesters', 'slug' => 'semesters.delete', 'group' => 'Configuration', 'description' => 'Delete unused semester options.'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);
        DB::table('permissions')->insert($permissions);

        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', self::REMOVED_PERMISSIONS)->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ])->all());
        }
    }
};
