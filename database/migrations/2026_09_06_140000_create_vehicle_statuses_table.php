<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_status', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'vehicle_status']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Vehicle Statuses', 'slug' => 'vehicle-statuses.view', 'group' => 'Vehicle Status Management', 'description' => 'View vehicle status records.'],
            ['name' => 'Create Vehicle Statuses', 'slug' => 'vehicle-statuses.create', 'group' => 'Vehicle Status Management', 'description' => 'Create vehicle status records.'],
            ['name' => 'Update Vehicle Statuses', 'slug' => 'vehicle-statuses.update', 'group' => 'Vehicle Status Management', 'description' => 'Update vehicle status details and status.'],
            ['name' => 'Delete Vehicle Statuses', 'slug' => 'vehicle-statuses.delete', 'group' => 'Vehicle Status Management', 'description' => 'Delete vehicle status records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['vehicle-statuses.view', 'vehicle-statuses.create', 'vehicle-statuses.update', 'vehicle-statuses.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicle_statuses');
    }
};
