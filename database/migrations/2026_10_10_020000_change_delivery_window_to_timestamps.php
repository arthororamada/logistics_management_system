<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dateTime('delivery_min_time')->nullable()->change();
            $table->dateTime('delivery_max_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('delivery_min_time')->nullable()->change();
            $table->unsignedInteger('delivery_max_time')->nullable()->change();
        });
    }
};
