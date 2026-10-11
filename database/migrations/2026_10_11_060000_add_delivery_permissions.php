<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create'] as $action) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => ucfirst($action).' Deliveries',
                'slug' => 'delivery.'.$action,
                'group' => 'Document Manifests',
                'description' => ucfirst($action).' delivery manifests.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roleId = DB::table('roles')->where('slug', 'administrator')->value('id');
            if ($roleId) {
                DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['delivery.view', 'delivery.create'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
