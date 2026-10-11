<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dm')->constrained('returns');
            $table->string('waybill_number');
            $table->enum('status', ['success', 'failed', 'dispatched', 'cancelled']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_details');
    }
};
