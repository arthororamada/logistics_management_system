<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->json('waybill_numbers');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (['view', 'create'] as $action) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => ucfirst($action).' Pickups',
                'slug' => 'pickup.'.$action,
                'group' => 'Document Manifests',
                'description' => ucfirst($action).' pickup manifests.',
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['pickup.view', 'pickup.create'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('pickups');
    }
};
