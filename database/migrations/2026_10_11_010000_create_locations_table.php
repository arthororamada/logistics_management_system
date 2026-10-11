<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('location_code', 30)->unique();
            $table->string('location_name', 150);
            $table->decimal('location_long', 11, 8);
            $table->decimal('location_lat', 10, 8);
            $table->timestamp('created_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('location_name');
        });

        $now = now();
        $permissions = collect([
            ['name' => 'View Locations', 'slug' => 'locations.view', 'description' => 'View location records.'],
            ['name' => 'Create Locations', 'slug' => 'locations.create', 'description' => 'Create location records.'],
            ['name' => 'Update Locations', 'slug' => 'locations.update', 'description' => 'Update location records.'],
            ['name' => 'Delete Locations', 'slug' => 'locations.delete', 'description' => 'Delete location records.'],
        ])->map(fn ($permission) => [
            ...$permission,
            'group' => 'Location Management',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permissions')->insert($permissions->all());
        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', $permissions->pluck('slug'))->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('locations');
    }
};
