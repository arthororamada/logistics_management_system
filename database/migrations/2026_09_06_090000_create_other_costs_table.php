<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_costs', function (Blueprint $table) {
            $table->id();
            $table->string('cost_code', 30)->unique();
            $table->string('cost_name', 150);
            $table->boolean('is_cash_release')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'cost_name']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Other Costs', 'slug' => 'other-costs.view', 'group' => 'Other Cost Management', 'description' => 'View other cost records.'],
            ['name' => 'Create Other Costs', 'slug' => 'other-costs.create', 'group' => 'Other Cost Management', 'description' => 'Create other cost records.'],
            ['name' => 'Update Other Costs', 'slug' => 'other-costs.update', 'group' => 'Other Cost Management', 'description' => 'Update other cost details and status.'],
            ['name' => 'Delete Other Costs', 'slug' => 'other-costs.delete', 'group' => 'Other Cost Management', 'description' => 'Delete other cost records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['other-costs.view', 'other-costs.create', 'other-costs.update', 'other-costs.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('other_costs');
    }
};
