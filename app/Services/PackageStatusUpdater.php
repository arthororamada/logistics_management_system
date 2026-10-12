<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PackageStatusUpdater
{
    public function update(array $waybillNumbers, string $status, int $userId): void
    {
        DB::transaction(function () use ($waybillNumbers, $status, $userId) {
            $packages = DB::table('packages')
                ->whereIn('waybill_number', $waybillNumbers)
                ->whereNull('is_deleted')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'waybill_number', 'package_status'])
                ->filter(fn ($package) => $package->package_status !== $status);

            if ($packages->isEmpty()) {
                return;
            }

            DB::table('packages')->whereIn('id', $packages->pluck('id'))
                ->update(['package_status' => $status]);

            // Status history belongs to the waybill, which may contain several packages.
            $timestamp = now();
            $logs = $packages->pluck('waybill_number')->unique()->map(fn ($number) => [
                'waybill_number' => $number,
                'status' => $status,
                'created_at' => $timestamp,
                'created_by' => $userId,
                'updated_at' => $timestamp,
                'updated_by' => $userId,
                'is_deleted' => null,
            ])->values()->all();

            foreach (array_chunk($logs, 100) as $chunk) {
                DB::table('status_logs')->insert($chunk);
            }
        });
    }
}
