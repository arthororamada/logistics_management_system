<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_additional_cost', function (Blueprint $table) {
            $table->decimal('liters_required', 14, 2)->nullable();
            $table->decimal('price_per_liter', 14, 2)->nullable();
            $table->foreignId('fuel_supplier')->nullable()->constrained('fuel_suppliers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_additional_cost', function (Blueprint $table) {
            $table->dropForeign(['fuel_supplier']);
            $table->dropColumn(['liters_required', 'price_per_liter', 'fuel_supplier']);
        });
    }
};
