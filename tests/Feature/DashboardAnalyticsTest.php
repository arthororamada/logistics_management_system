<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        $this->admin = User::create(['name' => 'Administrator', 'username' => 'admin', 'password' => 'test-password']);
        (require database_path('migrations/2026_08_31_010000_create_roles_and_permissions_tables.php'))->up();
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            foreach (['vehicle_status', 'plate_number', 'body_number', 'vehicle_group', 'vehicle_type'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('odometer')->default(0);
        });
        Schema::create('vehicle_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_status');
            $table->string('color');
        });
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code');
            $table->string('maintenance_description');
        });
        (require database_path('migrations/2026_10_11_020000_create_maintenances_tables.php'))->up();
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('current_status');
            $table->timestamp('date_completed')->nullable();
        });
        foreach (['pickups' => 'pickup', 'deliveries' => 'delivery', 'transfers' => 'transfer', 'returns' => 'return'] as $table => $module) {
            Schema::create($table, fn (Blueprint $schema) => $schema->id());
            Schema::create($module.'_details', function (Blueprint $schema) {
                $schema->id();
                $schema->unsignedBigInteger('dm');
                $schema->string('status');
            });
        }
    }

    public function test_dashboard_counts_actual_vehicles_alerts_and_pending_manifests(): void
    {
        DB::table('vehicle_statuses')->insert([
            ['vehicle_status' => 'Running', 'color' => '#16734A'],
            ['vehicle_status' => 'Repair', 'color' => '#8B1E2D'],
        ]);
        $first = DB::table('vehicles')->insertGetId(['vehicle_status' => 'Running', 'odometer' => 100]);
        $second = DB::table('vehicles')->insertGetId(['vehicle_status' => 'running', 'odometer' => 100]);
        DB::table('vehicles')->insert(['vehicle_status' => 'Custom status']);
        $this->maintenance($first, 'distance_policy', 90, 110);
        $this->maintenance($first, 'distance_policy', 90, 110);
        $this->maintenance($first, 'date_policy', '2026-10-01', '2026-10-12');
        $this->maintenance($second, 'date_policy', '2026-10-01', '2026-10-11');
        $this->maintenance($second, 'distance_policy', 90, 110, false);
        $this->maintenance($second, 'distance_policy', 110, 120);
        foreach ([$first, $first, $second, null] as $vehicle) {
            DB::table('trips')->insert(['vehicle_id' => $vehicle, 'current_status' => 'Dispatched']);
        }
        DB::table('trips')->insert(['vehicle_id' => $second, 'current_status' => 'Completed']);
        foreach (['pickups' => 'pickup', 'deliveries' => 'delivery', 'transfers' => 'transfer', 'returns' => 'return'] as $table => $module) {
            DB::table($table)->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
            DB::table($module.'_details')->insert([['dm' => 2, 'status' => 'dispatched'], ['dm' => 2, 'status' => 'dispatched'], ['dm' => 3, 'status' => 'success']]);
        }

        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $response->assertViewHas('totalVehicles', 3)
            ->assertViewHas('dispatchedVehicles', 2)
            ->assertViewHas('maintenanceVehicles', ['warning' => 1, 'violation' => 2])
            ->assertViewHas('pendingManifestTotal', 4)
            ->assertViewHas('pendingManifests', ['Pickup' => 1, 'Delivery' => 1, 'Transfer' => 1, 'Return' => 1]);
        $statuses = $response->viewData('vehicleStatuses')->keyBy('label');
        $this->assertSame(2, $statuses['Running']['total']);
        $this->assertSame('#16734A', $statuses['Running']['color']);
        $this->assertSame(0, $statuses['Repair']['total']);
        $this->assertSame(1, $statuses['Custom status']['total']);
        $response->assertSee('Vehicles by Status')->assertSee('Completed Trips Daily')->assertSee('Completed Trips Monthly')
            ->assertDontSee('Fleet Utilization')->assertDontSee('TR-1084');
    }

    public function test_completed_trip_charts_use_completion_dates_and_fill_empty_periods(): void
    {
        foreach (['2024-01-31 23:59:59', '2024-02-01 00:00:00', '2024-02-01 10:00:00', '2024-02-29 23:59:59', '2024-03-01 00:00:00', '2025-01-01 00:00:00'] as $date) {
            DB::table('trips')->insert(['current_status' => 'Completed', 'date_completed' => $date]);
        }
        DB::table('trips')->insert([
            ['current_status' => 'Cancelled', 'date_completed' => '2024-02-01 00:00:00'],
            ['current_status' => 'Dispatched', 'date_completed' => '2024-02-01 00:00:00'],
            ['current_status' => 'Completed', 'date_completed' => null],
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['month' => '2024-02', 'year' => 2024]))->assertOk();
        $daily = $response->viewData('dailyTrips');
        $monthly = $response->viewData('monthlyTrips');
        $this->assertCount(29, $daily);
        $this->assertSame(2, $daily[0]['total']);
        $this->assertSame(0, $daily[1]['total']);
        $this->assertSame(1, $daily[28]['total']);
        $this->assertCount(12, $monthly);
        $this->assertSame([1, 3, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], array_column($monthly, 'total'));
        $response->assertSee('2024-02-29: 1 completed trips')->assertSee('February 2024');
    }

    public function test_empty_dashboard_renders_zero_counts_and_current_periods(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('totalVehicles', 0)->assertViewHas('pendingManifestTotal', 0)
            ->assertViewHas('selectedMonth', '2026-10')->assertViewHas('selectedYear', 2026)
            ->assertSee('No completed trips in this period.');
        $this->assertCount(31, $response->viewData('dailyTrips'));
        $this->assertSame(0, array_sum(array_column($response->viewData('monthlyTrips'), 'total')));
    }

    public function test_dashboard_validates_chart_filters(): void
    {
        $this->actingAs($this->admin)->getJson(route('dashboard', ['month' => '2024-13', 'year' => 'bad']))
            ->assertUnprocessable()->assertJsonValidationErrors(['month', 'year']);
        $this->getJson(route('dashboard', ['year' => 2101]))->assertUnprocessable()->assertJsonValidationErrors('year');
    }

    public function test_dashboard_requires_authentication_and_permission(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $staff = User::create(['name' => 'Staff', 'username' => 'staff', 'password' => 'test-password']);
        $this->actingAs($staff)->get(route('dashboard'))->assertForbidden();
    }

    private function maintenance(int $vehicle, string $type, $warning, $due, bool $active = true): void
    {
        $policy = DB::table('vehicle_maintenances')->insertGetId(['maintenance_code' => 'POLICY-'.uniqid(), 'maintenance_description' => 'Test policy']);
        DB::table('maintenances')->insert([
            'vehicle_id' => $vehicle, 'vehicle_maintenance_id' => $policy,
            'policy_type' => $type, 'threshold' => 100, 'threshold_warning' => 10,
            'last_service_date' => '2026-01-01', 'last_service_odometer' => 0,
            $type === 'date_policy' ? 'warning_date' : 'warning_odometer' => $warning,
            $type === 'date_policy' ? 'next_due_date' : 'next_due_odometer' => $due,
            'is_active' => $active,
        ]);
    }
}
