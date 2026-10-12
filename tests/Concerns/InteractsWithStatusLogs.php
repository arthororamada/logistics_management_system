<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait InteractsWithStatusLogs
{
    protected function createStatusLogsTable(): void
    {
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('status', 100);
            $table->string('reference')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('is_deleted')->nullable();
        });
    }

    protected function assertStatusLogged(string $waybillNumber, string $status, int $userId): void
    {
        $this->assertDatabaseHas('status_logs', [
            'waybill_number' => $waybillNumber,
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
            'is_deleted' => null,
        ]);
    }
}
