<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Pickup;
use App\Models\ReturnManifest;
use App\Models\Transfer;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripWaybills
{
    public function model(?string $type): ?string
    {
        return match (strtolower(trim($type ?? ''))) {
            'delivery', 'local delivery' => Delivery::class,
            'pickup' => Pickup::class,
            'transfer' => Transfer::class,
            'return' => ReturnManifest::class,
            default => null,
        };
    }

    public function pending(?string $type)
    {
        $model = $this->model($type);

        return $model ? $model::where('status', 'pending')->orderBy('id')->get(['id', 'waybill_numbers']) : collect();
    }

    // Called inside the trip transaction. Lock manifests in a stable order so
    // competing dispatches cannot both claim the same pending manifest.
    public function validate(Trip $trip, ?string $type, array $rows): array
    {
        $model = $this->model($type);
        if (! $model || ! $rows) {
            throw ValidationException::withMessages(['waybills' => 'Select a supported trip type and add Waybill Information.']);
        }
        $manifests = $model::whereIn('id', array_column($rows, 'dm_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $existing = $trip->trip_type === $type && $trip->history()->where('status', 'Dispatched')->exists()
            ? $trip->waybills()->get()->groupBy('dm_id') : collect();
        $seen = [];
        foreach ($rows as $index => $row) {
            $manifest = $manifests->get($row['dm_id']);
            $previouslyDispatched = $manifest?->status === 'dispatched'
                && $existing->get($row['dm_id'], collect())->contains('waybill_number', $row['waybill_number']);
            if (! $manifest || ($manifest->status !== 'pending' && ! $previouslyDispatched)) {
                throw ValidationException::withMessages(["waybills.$index.dm_id" => 'Select a pending DM for the selected trip type.']);
            }
            if (! in_array($row['waybill_number'], $manifest->waybill_numbers, true)) {
                throw ValidationException::withMessages(["waybills.$index.waybill_number" => 'This waybill does not belong to the selected DM and trip type.']);
            }
            if (isset($seen[$row['waybill_number']])) {
                throw ValidationException::withMessages(["waybills.$index.waybill_number" => 'Each waybill can only appear once on a trip.']);
            }
            $seen[$row['waybill_number']] = true;
        }

        return $manifests->all();
    }

    public function dispatch(Trip $trip, int $userId): void
    {
        $rows = $trip->waybills()->get()->toArray();
        $manifests = $this->validate($trip, $trip->trip_type, $rows);
        $detailsTable = match ($this->model($trip->trip_type)) {
            Delivery::class => 'delivery_details',
            Pickup::class => 'pickup_details',
            Transfer::class => 'transfer_details',
            ReturnManifest::class => 'return_details',
        };
        foreach ($manifests as $manifest) {
            $manifest->update(['status' => 'dispatched']);
            DB::table($detailsTable)->where('dm', $manifest->id)->update(['status' => 'dispatched']);
            foreach ($manifest->waybill_numbers as $number) {
                DB::table($detailsTable)->updateOrInsert(
                    ['dm' => $manifest->id, 'waybill_number' => $number],
                    ['status' => 'dispatched'],
                );
            }
        }
        app(PackageStatusUpdater::class)->update(array_column($rows, 'waybill_number'), 'delivery_dispatched', $userId);
    }
}
