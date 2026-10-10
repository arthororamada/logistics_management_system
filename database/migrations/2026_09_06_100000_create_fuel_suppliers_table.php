<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('fuel_supplier_name', 150)->unique();
            $table->decimal('price_per_liter', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'fuel_supplier_name']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Fuel Suppliers', 'slug' => 'fuel-suppliers.view', 'group' => 'Fuel Supplier Management', 'description' => 'View fuel supplier records.'],
            ['name' => 'Create Fuel Suppliers', 'slug' => 'fuel-suppliers.create', 'group' => 'Fuel Supplier Management', 'description' => 'Create fuel supplier records.'],
            ['name' => 'Update Fuel Suppliers', 'slug' => 'fuel-suppliers.update', 'group' => 'Fuel Supplier Management', 'description' => 'Update fuel supplier details and status.'],
            ['name' => 'Delete Fuel Suppliers', 'slug' => 'fuel-suppliers.delete', 'group' => 'Fuel Supplier Management', 'description' => 'Delete fuel supplier records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['fuel-suppliers.view', 'fuel-suppliers.create', 'fuel-suppliers.update', 'fuel-suppliers.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('fuel_suppliers');
    }
};
