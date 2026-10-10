<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_lines', function (Blueprint $table) {
            $table->id();
            $table->string('shipping_line_name', 150)->unique();
            $table->text('billing_address');
            $table->string('focal_person', 150);
            $table->string('contact_number', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'shipping_line_name']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Shipping Lines', 'slug' => 'shipping-lines.view', 'group' => 'Shipping Line Management', 'description' => 'View shipping line records.'],
            ['name' => 'Create Shipping Lines', 'slug' => 'shipping-lines.create', 'group' => 'Shipping Line Management', 'description' => 'Create shipping line records.'],
            ['name' => 'Update Shipping Lines', 'slug' => 'shipping-lines.update', 'group' => 'Shipping Line Management', 'description' => 'Update shipping line details and status.'],
            ['name' => 'Delete Shipping Lines', 'slug' => 'shipping-lines.delete', 'group' => 'Shipping Line Management', 'description' => 'Delete shipping line records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['shipping-lines.view', 'shipping-lines.create', 'shipping-lines.update', 'shipping-lines.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('shipping_lines');
    }
};
