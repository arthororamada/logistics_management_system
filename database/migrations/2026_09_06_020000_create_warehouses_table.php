<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_code', 30)->unique();
            $table->string('warehouse_description', 150);
            $table->decimal('longitude', 10, 7);
            $table->decimal('latitude', 9, 7);
            $table->text('complete_address');
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('country', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'warehouse_description']);
            $table->index(['country', 'province', 'city']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Warehouses', 'slug' => 'warehouses.view', 'group' => 'Warehouse Management', 'description' => 'View warehouse records.'],
            ['name' => 'Create Warehouses', 'slug' => 'warehouses.create', 'group' => 'Warehouse Management', 'description' => 'Create warehouse records.'],
            ['name' => 'Update Warehouses', 'slug' => 'warehouses.update', 'group' => 'Warehouse Management', 'description' => 'Update warehouse details and status.'],
            ['name' => 'Delete Warehouses', 'slug' => 'warehouses.delete', 'group' => 'Warehouse Management', 'description' => 'Delete warehouse records.'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);
        DB::table('permissions')->insert($permissions);

        $administratorRoleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id');
            DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ])->all());
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('warehouses');
    }
};
