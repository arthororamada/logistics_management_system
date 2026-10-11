<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code', 30)->unique();
            $table->string('maintenance_description', 150);
            $table->string('vehicle_group', 150);
            $table->string('vehicle_type', 100);
            $table->enum('maintenance_type', ['distance_policy', 'date_policy']);
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('threshold_warning');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('update_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehicle_group', 'vehicle_type', 'is_active'], 'vehicle_maintenance_lookup_idx');
        });

        $now = now();
        $permissions = [];
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[] = [
                'name' => ucfirst($action).' Vehicle Maintenance',
                'slug' => "vehicle-maintenances.{$action}",
                'group' => 'Configuration',
                'description' => ucfirst($action).' vehicle maintenance policies.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('permissions')->insert($permissions);

        $administratorId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($administratorId) {
            DB::table('permission_role')->insert(
                DB::table('permissions')->whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id')
                    ->map(fn ($id) => ['permission_id' => $id, 'role_id' => $administratorId])->all()
            );
        }
    }

    public function down(): void
    {
        $slugs = collect(['view', 'create', 'update', 'delete'])->map(fn ($action) => "vehicle-maintenances.{$action}");
        $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('vehicle_maintenances');
    }
};
