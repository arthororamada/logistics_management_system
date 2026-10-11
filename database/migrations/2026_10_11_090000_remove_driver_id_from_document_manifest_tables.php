<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pickups', 'transfers', 'deliveries', 'returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('driver_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pickups', 'transfers', 'deliveries', 'returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('driver_id')->nullable()->constrained('personnel');
            });
        }
    }
};
