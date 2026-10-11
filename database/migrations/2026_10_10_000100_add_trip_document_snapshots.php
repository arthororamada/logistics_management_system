<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', fn (Blueprint $t) => $t->json('tariff_snapshot')->nullable());
        Schema::table('trip_personnels', fn (Blueprint $t) => $t->string('employee_name', 300)->nullable());
        Schema::table('trip_fuel_purchase', fn (Blueprint $t) => $t->string('supplier_name', 150)->nullable());
        Schema::table('trip_additional_cost', function (Blueprint $t) {
            $t->string('cost_name', 250)->nullable();
            $t->string('shipping_description', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $t) => $t->dropColumn('tariff_snapshot'));
        Schema::table('trip_personnels', fn (Blueprint $t) => $t->dropColumn('employee_name'));
        Schema::table('trip_fuel_purchase', fn (Blueprint $t) => $t->dropColumn('supplier_name'));
        Schema::table('trip_additional_cost', fn (Blueprint $t) => $t->dropColumn(['cost_name', 'shipping_description']));
    }
};
