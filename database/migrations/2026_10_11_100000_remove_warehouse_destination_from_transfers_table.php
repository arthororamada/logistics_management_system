<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('warehouse_destination');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Dropped destination values cannot be recovered for existing manifests.
            $table->string('warehouse_destination', 255)->default('');
        });
    }
};
