<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dm')->constrained('transfers');
            $table->string('waybill_number');
            $table->enum('status', ['transferred', 'cancelled', 'dispatched'])->default('dispatched');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_details');
    }
};
