<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_fuel_purchase', fn (Blueprint $table) => $table->text('remarks')->nullable());
    }

    public function down(): void
    {
        Schema::table('trip_fuel_purchase', fn (Blueprint $table) => $table->dropColumn('remarks'));
    }
};
