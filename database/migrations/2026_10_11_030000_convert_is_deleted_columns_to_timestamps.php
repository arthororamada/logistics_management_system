<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'packages' => ['package_status', 'is_deleted'],
        'items' => ['waybill_number', 'is_deleted'],
        'status_logs' => ['waybill_number', 'is_deleted', 'created_at'],
        'locations' => ['is_deleted', 'location_name'],
    ];

    private const WAYBILL_FOREIGN_KEY_TABLES = ['items', 'status_logs'];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexColumns) {
            if (Schema::hasColumn($tableName, 'is_deleted_timestamp')) {
                if (Schema::hasColumn($tableName, 'is_deleted')) {
                    $this->addTemporaryWaybillIndex($tableName);
                    if ($this->indexExists($tableName, $this->compositeIndexName($tableName, $indexColumns))) {
                        Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexColumns));
                    }
                    DB::table($tableName)
                        ->where('is_deleted', '<>', 0)
                        ->update(['is_deleted_timestamp' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('is_deleted'));
                }

                $this->renameTimestampColumn($tableName);
                if (!$this->indexExists($tableName, $this->compositeIndexName($tableName, $indexColumns))) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->index($indexColumns));
                }
                $this->dropTemporaryWaybillIndex($tableName);
                continue;
            }

            $column = collect(DB::select("SHOW COLUMNS FROM `{$tableName}` WHERE Field = 'is_deleted'"))->first();
            if ($column && str_starts_with(strtolower($column->Type), 'timestamp')) {
                continue;
            }

            $this->addTemporaryWaybillIndex($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($indexColumns) {
                $table->dropIndex($indexColumns);
                $table->timestamp('is_deleted_timestamp')->nullable()->after('is_deleted');
            });

            DB::table($tableName)
                ->where('is_deleted', '<>', 0)
                ->update(['is_deleted_timestamp' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);

            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('is_deleted'));
            $this->renameTimestampColumn($tableName);
            Schema::table($tableName, fn (Blueprint $table) => $table->index($indexColumns));
            $this->dropTemporaryWaybillIndex($tableName);
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexColumns) {
            $this->addTemporaryWaybillIndex($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($indexColumns) {
                $table->dropIndex($indexColumns);
                $table->boolean('is_deleted_boolean')->default(false)->after('is_deleted');
            });

            DB::table($tableName)
                ->whereNotNull('is_deleted')
                ->update(['is_deleted_boolean' => true]);

            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('is_deleted'));
            DB::statement("ALTER TABLE `{$tableName}` CHANGE `is_deleted_boolean` `is_deleted` TINYINT(1) NOT NULL DEFAULT 0");
            Schema::table($tableName, fn (Blueprint $table) => $table->index($indexColumns));
            $this->dropTemporaryWaybillIndex($tableName);
        }
    }

    private function renameTimestampColumn(string $tableName): void
    {
        DB::statement("ALTER TABLE `{$tableName}` CHANGE `is_deleted_timestamp` `is_deleted` TIMESTAMP NULL DEFAULT NULL");
    }

    private function addTemporaryWaybillIndex(string $tableName): void
    {
        $indexName = "{$tableName}_waybill_number_conversion_index";
        if (in_array($tableName, self::WAYBILL_FOREIGN_KEY_TABLES, true) && !$this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, fn (Blueprint $table) =>
                $table->index('waybill_number', $indexName)
            );
        }
    }

    private function dropTemporaryWaybillIndex(string $tableName): void
    {
        $indexName = "{$tableName}_waybill_number_conversion_index";
        if (in_array($tableName, self::WAYBILL_FOREIGN_KEY_TABLES, true) && $this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, fn (Blueprint $table) =>
                $table->dropIndex($indexName)
            );
        }
    }

    private function compositeIndexName(string $tableName, array $columns): string
    {
        return $tableName.'_'.implode('_', $columns).'_index';
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'")) > 0;
    }
};
