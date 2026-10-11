<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', fn (Blueprint $table) =>
            $table->string('package_status', 100)->default('for_pickup')->change()
        );
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $table) =>
            $table->string('package_status', 100)->change()
        );
    }
};
