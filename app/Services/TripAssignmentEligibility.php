<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripAssignmentEligibility
{
    public function availability(Trip $trip): array
    {
        $busy = Trip::where('current_status', 'Dispatched')->where('id', '!=', $trip->id);
        $vehicles = (clone $busy)->pluck('vehicle_id')->all();
        $employees = DB::table('trip_personnels')->whereIn('trip_id', (clone $busy)->select('trip_id'))->pluck('employee_id')->all();

        return [
            'vehicles' => Vehicle::all()->mapWithKeys(fn ($vehicle) => [$vehicle->id => $this->vehicleReason($vehicle, in_array($vehicle->id, $vehicles))])->all(),
            'personnels' => Personnel::all()->mapWithKeys(fn ($person) => [$person->id => $this->personnelReason($person, in_array($person->id, $employees))])->all(),
        ];
    }

    // The caller's transaction retains these resource locks through save/dispatch.
    // Always lock vehicle first, then personnel in ID order. Locking conflict reads
    // use the latest committed state even under MySQL's repeatable-read isolation.
    public function validate(Trip $trip, ?int $vehicleId, array $employeeIds): void
    {
        $vehicle = $vehicleId ? Vehicle::whereKey($vehicleId)->lockForUpdate()->firstOrFail() : null;
        $people = Personnel::whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $errors = [];
        if ($vehicle) {
            $busy = Trip::where('current_status', 'Dispatched')->where('id', '!=', $trip->id)
                ->where('vehicle_id', $vehicleId)->lockForUpdate()->first(['id']);
            if ($reason = $this->vehicleReason($vehicle, (bool) $busy)) {
                $errors['vehicle_id'] = $reason;
            }
        }
        $busyEmployees = DB::table('trip_personnels')->join('trips', 'trips.trip_id', '=', 'trip_personnels.trip_id')
            ->where('trips.current_status', 'Dispatched')->where('trips.id', '!=', $trip->id)
            ->whereIn('employee_id', $employeeIds)->lockForUpdate()->pluck('employee_id')->all();
        foreach ($employeeIds as $index => $id) {
            $person = $people->get($id);
            $reason = $person ? $this->personnelReason($person, in_array($id, $busyEmployees)) : 'The selected personnel no longer exists.';
            if ($reason) {
                $errors["personnels.$index.employee_id"] = $reason;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function vehicleReason(Vehicle $vehicle, bool $busy): ?string
    {
        return strcasecmp($vehicle->vehicle_status, 'Running') !== 0
            ? 'Only vehicles with Running status can be assigned to a trip.'
            : ($busy ? 'This vehicle is already assigned to a dispatched trip.' : null);
    }

    private function personnelReason(Personnel $person, bool $busy): ?string
    {
        return ! $person->is_active ? 'Only Active personnel can be assigned to a trip.'
            : ($busy ? 'This person is already assigned to a dispatched trip.' : null);
    }
}
