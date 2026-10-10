<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_code', 30)->unique();
            $table->string('client_name', 150);
            $table->text('full_address');
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('building_type', 100)->nullable();
            $table->string('focal_person', 150);
            $table->string('contact_number', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'client_name']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Clients', 'slug' => 'clients.view', 'group' => 'Client Management', 'description' => 'View client records.'],
            ['name' => 'Create Clients', 'slug' => 'clients.create', 'group' => 'Client Management', 'description' => 'Create client records.'],
            ['name' => 'Update Clients', 'slug' => 'clients.update', 'group' => 'Client Management', 'description' => 'Update client details and status.'],
            ['name' => 'Delete Clients', 'slug' => 'clients.delete', 'group' => 'Client Management', 'description' => 'Delete client records.'],
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
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('clients');
    }
};
