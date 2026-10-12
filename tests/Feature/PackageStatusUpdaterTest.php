<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PackageStatusUpdater;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithStatusLogs;
use Tests\TestCase;

class PackageStatusUpdaterTest extends TestCase
{
    use InteractsWithStatusLogs;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('package_status', 100);
            $table->timestamp('is_deleted')->nullable();
        });
        $this->createStatusLogsTable();
    }

    public function test_only_changed_active_waybills_are_logged_once_with_timestamps(): void
    {
        $user = User::create(['name' => 'Operator', 'username' => 'operator', 'password' => 'test-password']);
        DB::table('packages')->insert([
            ['waybill_number' => 'MULTIPLE', 'package_status' => 'for_pickup', 'is_deleted' => null],
            ['waybill_number' => 'MULTIPLE', 'package_status' => 'for_pickup', 'is_deleted' => null],
            ['waybill_number' => 'UNCHANGED', 'package_status' => 'for_pickup_dispatch', 'is_deleted' => null],
            ['waybill_number' => 'DELETED', 'package_status' => 'for_pickup', 'is_deleted' => now()],
            ['waybill_number' => 'UNSELECTED', 'package_status' => 'for_pickup', 'is_deleted' => null],
        ]);
        $this->freezeTime();

        $updater = app(PackageStatusUpdater::class);
        $updater->update(['MULTIPLE', 'MULTIPLE', 'UNCHANGED', 'DELETED', 'MISSING'], 'for_pickup_dispatch', $user->id);
        $updater->update(['MULTIPLE', 'UNCHANGED'], 'for_pickup_dispatch', $user->id);

        $this->assertDatabaseCount('status_logs', 1);
        $this->assertStatusLogged('MULTIPLE', 'for_pickup_dispatch', $user->id);
        $this->assertDatabaseHas('status_logs', ['created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(2, DB::table('packages')->where('waybill_number', 'MULTIPLE')->where('package_status', 'for_pickup_dispatch')->count());
        $this->assertDatabaseHas('packages', ['waybill_number' => 'DELETED', 'package_status' => 'for_pickup']);
        $this->assertDatabaseHas('packages', ['waybill_number' => 'UNSELECTED', 'package_status' => 'for_pickup']);
    }

    public function test_a_logging_failure_rolls_back_the_status_change(): void
    {
        DB::table('packages')->insert(['waybill_number' => 'WB-001', 'package_status' => 'for_pickup']);
        Schema::drop('status_logs');

        try {
            app(PackageStatusUpdater::class)->update(['WB-001'], 'for_pickup_dispatch', 1);
            $this->fail('Expected the log insert to fail.');
        } catch (QueryException $exception) {
            $this->assertDatabaseHas('packages', ['waybill_number' => 'WB-001', 'package_status' => 'for_pickup']);
        }
    }
}
