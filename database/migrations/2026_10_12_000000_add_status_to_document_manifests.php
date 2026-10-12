<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['deliveries', 'pickups', 'transfers', 'returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->enum('status', ['pending', 'dispatched', 'completed'])->default('pending');
            });
        }
    }

    public function down(): void
    {
        foreach (['deliveries', 'pickups', 'transfers', 'returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
