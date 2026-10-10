<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            'terms.view', 'terms.create', 'terms.update', 'terms.delete',
            'semesters.view', 'semesters.create', 'semesters.update', 'semesters.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('term_setups');
        Schema::dropIfExists('semesters');
    }
};
