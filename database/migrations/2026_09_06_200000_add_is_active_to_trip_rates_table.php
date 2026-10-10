<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('trip_rates', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('fuel_required')); } public function down(): void { Schema::table('trip_rates', fn (Blueprint $table) => $table->dropColumn('is_active')); } };
