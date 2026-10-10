<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('brgy', 100);
            $table->decimal('longitued', 10, 7);
            $table->decimal('latitude', 9, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'province', 'city', 'brgy']);
            $table->index(['latitude', 'longitued']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Routes', 'slug' => 'routes.view', 'group' => 'Route Management', 'description' => 'View route records.'],
            ['name' => 'Create Routes', 'slug' => 'routes.create', 'group' => 'Route Management', 'description' => 'Create route records.'],
            ['name' => 'Update Routes', 'slug' => 'routes.update', 'group' => 'Route Management', 'description' => 'Update route details and status.'],
            ['name' => 'Delete Routes', 'slug' => 'routes.delete', 'group' => 'Route Management', 'description' => 'Delete route records.'],
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
            'routes.view', 'routes.create', 'routes.update', 'routes.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('routes');
    }
};
