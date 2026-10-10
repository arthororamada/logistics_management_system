<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('api_key')->nullable()->after('contact_number');
            $table->text('token')->nullable()->after('api_key');
            $table->string('endpoint', 2048)->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'token', 'endpoint']);
        });
    }
};
