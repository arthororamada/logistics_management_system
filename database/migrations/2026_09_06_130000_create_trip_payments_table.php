<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_type', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'payment_type']);
        });

        $now = now();
        $permissions = [
            ['name' => 'View Trip Payments', 'slug' => 'trip-payments.view', 'group' => 'Trip Payment Management', 'description' => 'View trip payment records.'],
            ['name' => 'Create Trip Payments', 'slug' => 'trip-payments.create', 'group' => 'Trip Payment Management', 'description' => 'Create trip payment records.'],
            ['name' => 'Update Trip Payments', 'slug' => 'trip-payments.update', 'group' => 'Trip Payment Management', 'description' => 'Update trip payment details and status.'],
            ['name' => 'Delete Trip Payments', 'slug' => 'trip-payments.delete', 'group' => 'Trip Payment Management', 'description' => 'Delete trip payment records.'],
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
        $permissionIds = DB::table('permissions')->whereIn('slug', ['trip-payments.view', 'trip-payments.create', 'trip-payments.update', 'trip-payments.delete'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('trip_payments');
    }
};
