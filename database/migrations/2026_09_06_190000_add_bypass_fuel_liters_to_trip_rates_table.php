<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('trip_rates', fn (Blueprint $table) => $table->decimal('bypass_fuel_liters', 12, 2)->default(0)->after('additional_helper_rate')); }
 public function down(): void { Schema::table('trip_rates', fn (Blueprint $table) => $table->dropColumn('bypass_fuel_liters')); }
};
