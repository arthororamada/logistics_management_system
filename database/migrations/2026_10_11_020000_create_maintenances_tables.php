<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_maintenance_id')->constrained('vehicle_maintenances')->restrictOnDelete();
            $table->enum('policy_type', ['distance_policy', 'date_policy']);
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('threshold_warning');
            $table->date('last_service_date');
            $table->decimal('last_service_odometer', 12, 2);
            $table->date('warning_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('warning_odometer', 12, 2)->nullable();
            $table->decimal('next_due_odometer', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('update_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vehicle_id', 'vehicle_maintenance_id'], 'maintenance_vehicle_policy_unique');
            $table->index(['is_active', 'next_due_date']);
            $table->index(['is_active', 'next_due_odometer']);
        });

        Schema::create('maintenance_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->decimal('service_odometer', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['maintenance_id', 'service_date']);
        });

        $now = now();
        $permissions = [];
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[] = [
                'name' => ucfirst($action).' Maintenance',
                'slug' => "maintenances.{$action}",
                'group' => 'Fleet Operations',
                'description' => ucfirst($action).' per-vehicle maintenance cycles.',
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
        $slugs = collect(['view', 'create', 'update', 'delete'])->map(fn ($action) => "maintenances.{$action}");
        $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('maintenance_renewals');
        Schema::dropIfExists('maintenances');
    }
};
