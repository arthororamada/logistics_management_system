<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maintenance routes allow 100 characters each for province, city and barangay.
        Schema::table('trip_route', function (Blueprint $t) {
            $t->string('origin', 320)->change();
            $t->string('destination', 320)->change();
        });
    }

    public function down(): void
    {
        // Keep the wider labels on rollback to avoid truncating existing saved documents.
    }
};
