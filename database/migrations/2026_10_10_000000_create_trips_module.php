<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $t) {
            $t->id();
            $t->string('trip_id', 50)->unique();
            $t->uuid('creation_key')->unique();
            $t->unsignedInteger('version')->default(0);
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $t->foreignId('tariff_id')->nullable()->constrained('trip_rates')->restrictOnDelete();
            foreach (['van_number', 'vehicle_group', 'trip_no', 'trip_type', 'payment_type', 'trip_route_origin', 'trip_route_destination', 'trip_route_city', 'plate_number', 'fuel_type', 'engine_number', 'chassis_no', 'gcash_number'] as $field) {
                $t->string($field, 150)->nullable();
            }
            $t->index('van_number');
            $t->index('vehicle_group');
            $t->unsignedInteger('drops_number')->default(0);
            foreach (['total_distance', 'total_liters_required', 'fuel_capacity', 'cargo_capacity', 'fuel_price', 'total_released', 'total_nonreleased', 'total_cost'] as $field) {
                $t->decimal($field, 14, 2)->default(0);
            }
            $t->decimal('fuel_usage', 10, 3)->default(0);
            $t->string('current_status', 20)->default('Open')->index();
            $t->timestamp('date_completed')->nullable();
            foreach (['created_by', 'updated_by', 'approved_by'] as $field) {
                $t->foreignId($field)->nullable()->constrained('users')->restrictOnDelete();
            }
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        foreach (['trip_personnels', 'trip_route', 'trip_additional_cost', 'trip_fuel_purchase', 'trip_status'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->string('trip_id', 50);
                $t->foreign('trip_id')->references('trip_id')->on('trips')->cascadeOnDelete();
                $t->index('trip_id');
                if ($table === 'trip_personnels') {
                    $t->foreignId('employee_id')->constrained('personnel')->restrictOnDelete();
                    $t->string('designation', 100)->index();
                    $t->decimal('cash_released', 14, 2)->default(0);
                    $t->decimal('non_cash_released', 14, 2)->default(0);
                    $t->decimal('salary', 8, 2)->default(0);
                    $t->unique(['trip_id', 'employee_id']);
                } elseif ($table === 'trip_route') {
                    $t->string('origin', 150)->index();
                    $t->string('destination', 150);
                    $t->foreignId('origin_id')->constrained('routes')->restrictOnDelete();
                    $t->foreignId('destination_id')->constrained('routes')->restrictOnDelete();
                    $t->decimal('total_distance', 14, 2)->default(0);
                } elseif ($table === 'trip_fuel_purchase') {
                    $t->foreignId('fuel_supplier')->constrained('fuel_suppliers')->restrictOnDelete();
                    foreach (['liters_required', 'price_per_liter', 'fuel_price'] as $field) {
                        $t->decimal($field, 14, 2)->default(0);
                    }
                } elseif ($table === 'trip_additional_cost') {
                    $t->string('costgroup', 100)->nullable();
                    $t->foreignId('cost_code')->constrained('other_costs')->restrictOnDelete();
                    $t->foreignId('shipping_rate_id')->nullable()->constrained('shipping_rates')->restrictOnDelete();
                    $t->text('remarks')->nullable();
                    $t->boolean('is_cash_released')->default(false);
                    $t->decimal('nonreleased_amount', 14, 2)->default(0);
                    $t->decimal('released_amount', 14, 2)->default(0);
                    $t->string('reference_id', 150)->nullable();
                } else {
                    $t->string('status', 20);
                    $t->text('reason')->nullable();
                    $t->timestamp('archived_at')->nullable();
                    $t->unique(['trip_id', 'status']);
                }
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                if ($table !== 'trip_status') {
                    $t->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
                }
                $t->timestamps();
            });
        }
        foreach (['view', 'create', 'update', 'dispatch', 'complete', 'manage-all'] as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => ucfirst($action).' Trips', 'slug' => 'trips.'.$action,
                'group' => 'Fleet Operations', 'description' => ucfirst($action).' trips.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $role = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($role) {
            foreach (DB::table('permissions')->where('slug', 'like', 'trips.%')->pluck('id') as $id) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $id, 'role_id' => $role]);
            }
        }
    }

    public function down(): void
    {
        foreach (['trip_status', 'trip_fuel_purchase', 'trip_additional_cost', 'trip_route', 'trip_personnels', 'trips'] as $table) {
            Schema::dropIfExists($table);
        }
        $ids = DB::table('permissions')->where('slug', 'like', 'trips.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
