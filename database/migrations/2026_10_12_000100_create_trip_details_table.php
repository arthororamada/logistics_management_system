<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_details', function (Blueprint $table) {
            $table->id();
            $table->string('trip_id');
            $table->integer('dm_id');
            $table->string('waybill_number');
            $table->integer('quantity')->default(0);
            $table->integer('kilogram')->default(0);
            $table->float('cbm')->default(0);
            $table->integer('drops_number');
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_details');
    }
};
