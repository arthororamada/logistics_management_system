<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code', 30)->unique();
            $table->text('program_description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'program_code']);
        });

        Schema::create('campus_program', function (Blueprint $table) {
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->primary(['campus_id', 'program_id']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Programs', 'slug' => 'programs.view', 'group' => 'Program Management', 'description' => 'View academic program records.'],
            ['name' => 'Create Programs', 'slug' => 'programs.create', 'group' => 'Program Management', 'description' => 'Create programs and assign campuses.'],
            ['name' => 'Update Programs', 'slug' => 'programs.update', 'group' => 'Program Management', 'description' => 'Update programs and campus assignments.'],
            ['name' => 'Delete Programs', 'slug' => 'programs.delete', 'group' => 'Program Management', 'description' => 'Delete program records.'],
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
            'programs.view', 'programs.create', 'programs.update', 'programs.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('campus_program');
        Schema::dropIfExists('programs');
    }
};
