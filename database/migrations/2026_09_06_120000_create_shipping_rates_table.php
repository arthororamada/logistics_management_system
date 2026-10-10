<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('shipping_line_name', 150);
            $table->string('port_origin', 150);
            $table->string('port_destination', 150);
            $table->string('vehicle_type', 100);
            $table->decimal('shippine_rate', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'shipping_line_name']);
            $table->index(['port_origin', 'port_destination']);
            $table->index(['vehicle_type', 'is_active']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Shipping Rates', 'slug' => 'shipping-rates.view', 'group' => 'Shipping Rate Management', 'description' => 'View shipping rate records.'],
            ['name' => 'Create Shipping Rates', 'slug' => 'shipping-rates.create', 'group' => 'Shipping Rate Management', 'description' => 'Create shipping rate records.'],
            ['name' => 'Update Shipping Rates', 'slug' => 'shipping-rates.update', 'group' => 'Shipping Rate Management', 'description' => 'Update shipping rate details and status.'],
            ['name' => 'Delete Shipping Rates', 'slug' => 'shipping-rates.delete', 'group' => 'Shipping Rate Management', 'description' => 'Delete shipping rate records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['shipping-rates.view', 'shipping-rates.create', 'shipping-rates.update', 'shipping-rates.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('shipping_rates');
    }
};
