<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Pickup;
use App\Models\ReturnManifest;
use App\Models\Transfer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentManifestStatusTest extends TestCase
{
    private const MODELS = [Delivery::class, Pickup::class, Transfer::class, ReturnManifest::class];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        foreach (self::MODELS as $modelClass) {
            $tableName = (new $modelClass)->getTable();
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->json('waybill_numbers');
                $table->timestamps();
            });
            DB::table($tableName)->insert(['waybill_numbers' => '["EXISTING"]']);
        }

        (require database_path('migrations/2026_10_12_000000_add_status_to_document_manifests.php'))->up();
    }

    public function test_existing_and_new_manifests_default_to_pending(): void
    {
        foreach (self::MODELS as $modelClass) {
            $tableName = (new $modelClass)->getTable();
            $this->assertDatabaseHas($tableName, ['id' => 1, 'status' => 'pending']);

            $manifest = $modelClass::create(['waybill_numbers' => ['NEW']]);
            $this->assertSame('pending', $manifest->status);
            $this->assertSame('pending', $manifest->fresh()->status);

            $id = DB::table($tableName)->insertGetId(['waybill_numbers' => '["RAW"]']);
            $this->assertDatabaseHas($tableName, ['id' => $id, 'status' => 'pending']);
        }
    }

    public function test_manifests_support_dispatched_and_completed_statuses(): void
    {
        foreach (self::MODELS as $modelClass) {
            foreach (['dispatched', 'completed'] as $status) {
                $manifest = $modelClass::create(['waybill_numbers' => ['WB-001'], 'status' => $status]);
                $this->assertSame($status, $manifest->fresh()->status);
            }
        }
    }

    /** @dataProvider manifestTables */
    public function test_database_rejects_invalid_statuses(string $tableName): void
    {
        $this->expectException(QueryException::class);
        DB::table($tableName)->insert(['waybill_numbers' => '["INVALID"]', 'status' => 'invalid']);
    }

    public static function manifestTables(): array
    {
        return [['deliveries'], ['pickups'], ['transfers'], ['returns']];
    }

    public function test_status_migration_can_be_rolled_back_without_removing_manifests(): void
    {
        (require database_path('migrations/2026_10_12_000000_add_status_to_document_manifests.php'))->down();

        foreach (self::MODELS as $modelClass) {
            $tableName = (new $modelClass)->getTable();
            $this->assertFalse(Schema::hasColumn($tableName, 'status'));
            $this->assertDatabaseCount($tableName, 1);
        }
    }
}
