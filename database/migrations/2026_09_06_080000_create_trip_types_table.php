<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_types', function (Blueprint $table) {
            $table->id();
            $table->string('trip_type', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'trip_type']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Trip Types', 'slug' => 'trip-types.view', 'group' => 'Trip Type Management', 'description' => 'View trip type records.'],
            ['name' => 'Create Trip Types', 'slug' => 'trip-types.create', 'group' => 'Trip Type Management', 'description' => 'Create trip type records.'],
            ['name' => 'Update Trip Types', 'slug' => 'trip-types.update', 'group' => 'Trip Type Management', 'description' => 'Update trip type details and status.'],
            ['name' => 'Delete Trip Types', 'slug' => 'trip-types.delete', 'group' => 'Trip Type Management', 'description' => 'Delete trip type records.'],
        ];
        foreach ($permissions as &$permission) { $permission['created_at'] = $now; $permission['updated_at'] = $now; }
        unset($permission);
        DB::table('permissions')->insert($permissions);
        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $administratorRoleId])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['trip-types.view', 'trip-types.create', 'trip-types.update', 'trip-types.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('trip_types');
    }
};
