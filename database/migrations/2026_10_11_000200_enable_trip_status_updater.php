<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Row locks and status/version checks protect transitions; repeated statuses need audit entries.
        Schema::table('trip_status', fn (Blueprint $table) => $table->dropUnique(['trip_id', 'status']));
        foreach (['view', 'update'] as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => ucfirst($action).' Trip Status Updater', 'slug' => 'trip-status-updaters.'.$action,
                'group' => 'Trip Maintenance', 'description' => ucfirst($action).' trips in the Trip Status Updater.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $role = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($role) {
            foreach (DB::table('permissions')->whereIn('slug', ['trip-status-updaters.view', 'trip-status-updaters.update'])->pluck('id') as $id) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $id, 'role_id' => $role]);
            }
        }
    }

    public function down(): void
    {
        // Restoring the constraint fails safely if repeated audit records already exist.
        Schema::table('trip_status', fn (Blueprint $table) => $table->unique(['trip_id', 'status']));
        $ids = DB::table('permissions')->whereIn('slug', ['trip-status-updaters.view', 'trip-status-updaters.update'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
