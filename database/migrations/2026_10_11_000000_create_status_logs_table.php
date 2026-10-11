<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('status', 100);
            $table->string('reference', 255)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);

            $table->foreign('waybill_number')
                ->references('waybill_number')
                ->on('packages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['waybill_number', 'is_deleted', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
    }
};
