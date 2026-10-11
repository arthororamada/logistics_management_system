<?php

namespace App\Services;

use App\Models\FuelSupplier;
use App\Models\OtherCost;
use App\Models\Personnel;
use App\Models\Route;
use App\Models\ShippingRate;
use App\Models\Trip;
use App\Models\TripRate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TripService
{
    // Caller holds the trip row lock for the entire save/transition transaction.
    public function save(Trip $trip, array $data, int $userId): void
    {
        abort_unless($trip->current_status === 'Open', 409, 'Only open trips can be edited.');
        abort_unless((int) $data['version'] === (int) $trip->version, 409, 'This trip changed. Reload before saving.');
        app(TripAssignmentEligibility::class)->validate($trip, $data['vehicle_id'] ?? null, array_column($data['personnels'], 'employee_id'));
        $this->prepareFuelCosts($trip, $data, $userId);
        $trip->fill(Arr::only($data, ['vehicle_id', 'tariff_id', 'trip_no', 'trip_type', 'payment_type', 'drops_number', 'gcash_number']));
        $vehicle = $trip->vehicle_id ? Vehicle::findOrFail($trip->vehicle_id) : null;
        foreach (['van_number' => 'body_number', 'vehicle_group' => 'vehicle_group', 'plate_number' => 'plate_number', 'fuel_type' => 'fuel_type', 'engine_number' => 'engine_number', 'chassis_no' => 'chassi_number', 'fuel_capacity' => 'fuel_capacity', 'fuel_usage' => 'fuel_usage_per_kilometer', 'cargo_capacity' => 'cargo_capacity'] as $target => $source) {
            $trip->$target = $vehicle?->$source ?? (in_array($target, ['fuel_capacity', 'fuel_usage', 'cargo_capacity']) ? 0 : null);
        }
        $tariff = $trip->tariff_id ? TripRate::findOrFail($trip->tariff_id) : null;
        if ($tariff) {
            if (! $vehicle || $tariff->vehicle_group !== $vehicle->vehicle_group || $tariff->vehicle_type !== $vehicle->vehicle_type || $tariff->warehouse_code !== $vehicle->warehouse_code || $tariff->trip_type !== $trip->trip_type || $tariff->payment_type !== $trip->payment_type || (float) $vehicle->cargo_capacity < (float) $tariff->cargo_capacity) {
                throw ValidationException::withMessages(['tariff_id' => 'Trip Rate must match the vehicle warehouse, group, type, capacity, trip type and payment type.']);
            }
        }
        $released = $nonreleased = $fuelCents = 0;
        $trip->tariff_snapshot = $tariff?->toArray();
        $existingPersonnel = $trip->personnels()->get();
        $existingByEmployee = $existingPersonnel->keyBy('employee_id');
        $incomingEmployees = array_column($data['personnels'], 'employee_id');
        foreach ($existingPersonnel as $assignment) {
            if ($this->cents($assignment->cash_released) > 0 && ! in_array($assignment->employee_id, $incomingEmployees)) {
                throw ValidationException::withMessages(['personnels' => 'Personnel with released funds cannot be removed.']);
            }
        }
        $disbursements = collect($data['disbursements'] ?? [])->keyBy('employee_id');
        foreach ($disbursements as $employeeId => $disbursement) {
            if (! in_array($employeeId, $incomingEmployees)) {
                throw ValidationException::withMessages(['disbursements' => 'Disbursements must belong to personnel assigned to this trip.']);
            }
        }
        foreach ($data['personnels'] as &$person) {
            $employee = Personnel::findOrFail($person['employee_id']);
            $person['employee_name'] = $employee->employee_id.' - '.$employee->first_name.' '.$employee->last_name;
            $salary = $tariff?->personnelSalaryCents($person['designation'], (int) $trip->drops_number);
            if ($salary === null) {
                throw ValidationException::withMessages(['personnels' => 'Select a Trip Rate with a configured rate for each designation.']);
            }
            if ($salary > 99999999) {
                throw ValidationException::withMessages(['personnels' => 'Calculated salary exceeds the supported amount.']);
            }
            $previous = $existingByEmployee->get($person['employee_id']);
            $previousRelease = $this->cents($previous?->cash_released ?? 0);
            $requestedRelease = $disbursements->get($person['employee_id']);
            $cash = $requestedRelease ? $this->cents($requestedRelease['cash_released']) : $previousRelease;
            if ($cash < $previousRelease) {
                throw ValidationException::withMessages(['disbursements' => 'Already released funds cannot be reduced through the standard editing interface.']);
            }
            if ($cash > $salary) {
                throw ValidationException::withMessages(['disbursements' => 'Calculated salary cannot be less than the amount already released or requested for release.']);
            }
            if (! empty($person['id'])) {
                $original = $existingPersonnel->firstWhere('id', $person['id']);
                if ($original && $original->employee_id != $person['employee_id'] && $this->cents($original->cash_released) > 0) {
                    throw ValidationException::withMessages(['personnels' => 'An assignment with released funds cannot be reassigned to another employee.']);
                }
            }
            $person['salary'] = $salary / 100;
            $person['cash_released'] = $cash / 100;
            $person['non_cash_released'] = ($salary - $cash) / 100;
            $released += $cash;
            $nonreleased += $salary - $cash;
        }
        unset($person);
        $distance = 0;
        foreach ($data['segments'] as &$segment) {
            $segment['origin'] = $this->routeName(Route::findOrFail($segment['origin_id']));
            $segment['destination'] = $this->routeName(Route::findOrFail($segment['destination_id']));
            $distance += $this->cents($segment['total_distance']);
        }
        unset($segment);
        $firstSegment = $data['segments'][0] ?? null;
        $lastSegment = $data['segments'] ? $data['segments'][array_key_last($data['segments'])] : null;
        $trip->trip_route_origin = $firstSegment ? Route::findOrFail($firstSegment['origin_id'])->city : null;
        $trip->trip_route_destination = $lastSegment ? Route::findOrFail($lastSegment['destination_id'])->city : null;
        $trip->trip_route_city = $trip->trip_route_origin;
        foreach ($data['fuel_purchases'] as &$fuel) {
            $fuel['supplier_name'] = FuelSupplier::findOrFail($fuel['fuel_supplier'])->fuel_supplier_name;
            $fuel['fuel_price'] = round((float) $fuel['liters_required'] * (float) $fuel['price_per_liter'], 2);
            if ($this->cents($fuel['fuel_price']) > 99999999) {
                throw ValidationException::withMessages(['fuel_purchases' => 'Calculated fuel cost exceeds the supported amount.']);
            }
            $fuelCents += $this->cents($fuel['fuel_price']);
        }
        unset($fuel);
        $existingCosts = $trip->costs()->get()->keyBy('id');
        foreach ($data['costs'] as $index => &$cost) {
            $definition = OtherCost::findOrFail($cost['cost_code']);
            $type = strtoupper(trim($definition->cost_code));
            $modern = array_key_exists('cost_amount', $cost);
            $original = $existingCosts->get($cost['id'] ?? null);
            if ($modern) {
                $cost['reference_id'] = $original?->reference_id;
                $cost['costgroup'] = $original?->costgroup;
            }
            if (in_array($type, ['FUEL_PURCHASE', 'CASH_FUEL'])) {
                foreach (['liters_required', 'price_per_liter'] as $field) {
                    if (! isset($cost[$field])) {
                        throw ValidationException::withMessages(["costs.$index.$field" => 'Required for cash fuel costs.']);
                    }
                }
                if ($type === 'FUEL_PURCHASE' && empty($cost['fuel_supplier'])) {
                    throw ValidationException::withMessages(["costs.$index.fuel_supplier" => 'Select a fuel supplier.']);
                }
                $cost['cost_amount'] = round((float) $cost['liters_required'] * (float) $cost['price_per_liter'], 2);
                $modern = true;
            } else {
                $cost['liters_required'] = $cost['price_per_liter'] = null;
            }
            if ($type === 'RORO_COST' && (empty($cost['shipping_rate_id']) || empty($cost['shipping_line_name']))) {
                throw ValidationException::withMessages(["costs.$index.shipping_rate_id" => 'Select a shipping line and route.']);
            }
            if ($modern && $type !== 'RORO_COST') {
                $cost['shipping_rate_id'] = null;
            }
            if ($modern) {
                $amount = $this->cents($cost['cost_amount']);
                if ($amount > 99999999) {
                    throw ValidationException::withMessages(["costs.$index.cost_amount" => 'Calculated cost exceeds the supported amount.']);
                }
                $cost['released_amount'] = $definition->is_cash_release ? $amount / 100 : 0;
                $cost['nonreleased_amount'] = $definition->is_cash_release ? 0 : $amount / 100;
            } elseif (! isset($cost['released_amount'], $cost['nonreleased_amount'])) {
                throw ValidationException::withMessages(["costs.$index.cost_amount" => 'Enter a cost amount.']);
            }
            $cost['cost_name'] = $definition->cost_code.' · '.$definition->cost_name;
            $cost['shipping_description'] = null;
            $cost['is_cash_released'] = $definition->is_cash_release;
            if (! empty($cost['shipping_rate_id'])) {
                $shipping = ShippingRate::findOrFail($cost['shipping_rate_id']);
                if ($type === 'RORO_COST' && $shipping->shipping_line_name !== $cost['shipping_line_name']) {
                    throw ValidationException::withMessages(["costs.$index.shipping_rate_id" => 'Shipping route must belong to the selected shipping line.']);
                }
                if ($modern && $this->cents($shipping->shippine_rate) > 99999999) {
                    throw ValidationException::withMessages(["costs.$index.cost_amount" => 'Shipping rate exceeds the supported amount.']);
                }
                $cost['shipping_description'] = $shipping->shipping_line_name.' · '.$shipping->port_origin.' → '.$shipping->port_destination.' · '.$shipping->vehicle_type;
                if (! $vehicle || $shipping->vehicle_type !== $vehicle->vehicle_type) {
                    throw ValidationException::withMessages(['costs' => 'Shipping rate must match the selected vehicle type.']);
                }
                $cost['released_amount'] = $cost['is_cash_released'] ? $shipping->shippine_rate : 0;
                $cost['nonreleased_amount'] = $cost['is_cash_released'] ? 0 : $shipping->shippine_rate;
            }
            if (($cost['is_cash_released'] && $this->cents($cost['nonreleased_amount']) > 0) || (! $cost['is_cash_released'] && $this->cents($cost['released_amount']) > 0)) {
                throw ValidationException::withMessages(['costs' => 'Cost amounts must follow the Other Costs cash release setting.']);
            }
            $released += $this->cents($cost['released_amount']);
            $nonreleased += $this->cents($cost['nonreleased_amount']);
            if ($type !== 'FUEL_PURCHASE') {
                $cost['fuel_supplier'] = null;
            }
            unset($cost['cost_amount'], $cost['shipping_line_name']);
        }
        unset($cost);
        foreach (['personnels' => 'personnels', 'segments' => 'segments', 'fuel_purchases' => 'fuelPurchases', 'costs' => 'costs'] as $key => $relation) {
            $submittedIds = array_filter(array_column($data[$key], 'id'));
            if ($trip->$relation()->whereIn('id', $submittedIds)->count() !== count($submittedIds)) {
                throw ValidationException::withMessages([$key => 'A related record does not belong to this trip.']);
            }
            // Remove omitted rows first so removing/re-adding an employee remains valid.
            $trip->$relation()->whereNotIn('id', $submittedIds)->delete();
            $ids = [];
            foreach ($data[$key] as $row) {
                $id = Arr::pull($row, 'id');
                $record = $id ? $trip->$relation()->find($id) : null;
                if ($id && ! $record) {
                    throw ValidationException::withMessages([$key => 'A related record does not belong to this trip.']);
                }
                if ($key === 'personnels' && $trip->personnels()->where('employee_id', $row['employee_id'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    throw ValidationException::withMessages(['personnels' => 'This employee already has an assignment. Remove the old assignment and save before reassigning.']);
                }
                $row['updated_by'] = $userId;
                if ($record) {
                    $record->update($row);
                } else {
                    $record = $trip->$relation()->create($row + ['created_by' => $userId]);
                }
                $ids[] = $record->id;
            }
            $trip->$relation()->whereNotIn('id', $ids)->delete();
        }
        $trip->total_distance = $distance / 100;
        $trip->total_liters_required = $tariff?->is_bypass_fuel_usage ? $tariff->bypass_fuel_liters : round($trip->total_distance * (float) $trip->fuel_usage, 2);
        $trip->fuel_price = $fuelCents / 100;
        $trip->total_released = $released / 100;
        // Fuel purchases are payable costs; no fuel cash-release flag exists in the schema.
        $trip->total_nonreleased = ($nonreleased + $fuelCents) / 100;
        $trip->total_cost = ($released + $nonreleased + $fuelCents) / 100;
        foreach (['total_distance', 'total_liters_required', 'fuel_price', 'total_released', 'total_nonreleased', 'total_cost'] as $field) {
            if ($trip->$field > 999999999999.99) {
                throw ValidationException::withMessages([$field => 'Calculated total exceeds the supported amount.']);
            }
        }
        $trip->updated_by = $userId;
        $trip->version++;
        $trip->save();
    }

    public function dispatch(Trip $trip, int $userId, ?string $reason): void
    {
        abort_unless($trip->current_status === 'Open', 409, 'Only open trips can be dispatched.');
        app(TripAssignmentEligibility::class)->validate($trip, $trip->vehicle_id, $trip->personnels()->pluck('employee_id')->all());
        $errors = [];
        foreach (['vehicle_id', 'trip_no', 'trip_type', 'payment_type', 'trip_route_origin', 'trip_route_destination', 'tariff_id'] as $field) {
            if (! $trip->$field) {
                $errors[$field] = 'Required before dispatch.';
            }
        }
        if (! $trip->personnels()->whereRaw('LOWER(designation) = ?', ['driver'])->exists()) {
            $errors['personnels'] = 'Assign a driver before dispatch.';
        }
        if (! $trip->segments()->exists() || $trip->total_distance <= 0) {
            $errors['segments'] = 'Add a route with a positive distance before dispatch.';
        }
        foreach (['personnels' => ['employee_id', 'personnel'], 'fuelPurchases' => ['fuel_supplier', 'fuel_suppliers'], 'costs' => ['cost_code', 'other_costs']] as $relation => [$field, $table]) {
            $ids = $trip->$relation()->pluck($field)->unique();
            if (\Illuminate\Support\Facades\DB::table($table)->whereIn('id', $ids)->where('is_active', true)->count() !== $ids->count()) {
                $errors[$relation] = 'An assigned maintenance record is inactive. Update the trip before dispatch.';
            }
        }
        if ($trip->tariff_id && ! TripRate::whereKey($trip->tariff_id)->where('is_active', true)->exists()) {
            $errors['tariff_id'] = 'The selected Trip Rate is inactive. Select an active Trip Rate before dispatch.';
        }
        $routeIds = $trip->segments()->pluck('origin_id')->merge($trip->segments()->pluck('destination_id'))->unique();
        if (Route::whereIn('id', $routeIds)->where('is_active', true)->count() !== $routeIds->count()) {
            $errors['segments'] = 'An assigned route is inactive. Update the trip before dispatch.';
        }
        $shippingIds = $trip->costs()->whereNotNull('shipping_rate_id')->pluck('shipping_rate_id')->unique();
        $supplierIds = $trip->costs()->whereNotNull('fuel_supplier')->pluck('fuel_supplier')->unique();
        if (FuelSupplier::whereIn('id', $supplierIds)->where('is_active', true)->count() !== $supplierIds->count()) {
            $errors['costs'] = 'An assigned fuel supplier is inactive. Update the trip before dispatch.';
        }
        if (ShippingRate::whereIn('id', $shippingIds)->where('is_active', true)->count() !== $shippingIds->count()) {
            $errors['costs'] = 'An assigned shipping rate is inactive. Update the trip before dispatch.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $this->transition($trip, 'Dispatched', $userId, $reason);
    }

    public function complete(Trip $trip, int $userId, ?string $reason): void
    {
        abort_unless($trip->current_status === 'Dispatched', 409, 'Only dispatched trips can be completed.');
        $trip->date_completed = now();
        $this->transition($trip, 'Completed', $userId, $reason);
    }

    public function updateStatus(Trip $trip, string $status, string $reason, int $version, int $userId): void
    {
        abort_unless($version === (int) $trip->version, 409, 'This trip changed. Search for the trip again before updating its status.');
        if (! in_array($status, ['Open', 'Cancelled']) || trim($reason) === '') {
            throw ValidationException::withMessages(['new_status' => 'Select Open or Cancelled and provide a reason.']);
        }
        if ($trip->current_status === $status) {
            throw ValidationException::withMessages(['new_status' => 'Select a status different from the current status.']);
        }
        if ($status === 'Open') {
            $trip->date_completed = null;
        }
        $this->transition($trip, $status, $userId, trim($reason));
    }

    private function transition(Trip $trip, string $status, int $userId, ?string $reason): void
    {
        $trip->current_status = $status;
        $trip->updated_by = $userId;
        $trip->version++;
        $trip->save();
        $trip->history()->create(['status' => $status, 'reason' => $reason, 'created_by' => $userId]);
    }

    private function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    private function prepareFuelCosts(Trip $trip, array &$data, int $userId): void
    {
        $available = OtherCost::availableTo(User::findOrFail($userId))->get()->keyBy('id');
        $existingFuelIds = $trip->fuelPurchases()->pluck('id')->all();
        $existingCosts = $trip->costs()->get()->keyBy('id');
        foreach ($data['costs'] as $index => &$cost) {
            $fuelId = $cost['fuel_purchase_id'] ?? null;
            $costId = $cost['id'] ?? null;
            if ($costId && ! $existingCosts->has($costId)) {
                throw ValidationException::withMessages(['costs' => 'A related record does not belong to this trip.']);
            }
            if ($fuelId && (! in_array($fuelId, $existingFuelIds) || $costId)) {
                throw ValidationException::withMessages(["costs.$index.fuel_purchase_id" => 'Fuel record does not belong to this trip cost.']);
            }
            $definition = $available->get($cost['cost_code'] ?? null);
            // Existing fuel records predate the configurable fuel cost code.
            if (! $definition && (! $fuelId || ! empty($cost['cost_code']))) {
                throw ValidationException::withMessages(["costs.$index.cost_code" => 'Select a cost code available to you.']);
            }
            $type = $definition ? strtoupper(trim($definition->cost_code)) : 'FUEL_PURCHASE';
            unset($cost['fuel_purchase_id']);
            if ($type !== 'FUEL_PURCHASE') {
                continue;
            }
            // Preserve fuel costs saved by the earlier additional-cost form.
            if ($costId && $existingCosts->get($costId)->cost_code == $cost['cost_code']) {
                continue;
            }
            if ($costId && $existingCosts->get($costId)->reference_id) {
                throw ValidationException::withMessages(["costs.$index.cost_code" => 'A cost with a cash release reference cannot be converted to a fuel purchase.']);
            }
            foreach (['fuel_supplier', 'liters_required', 'price_per_liter'] as $field) {
                if (! isset($cost[$field])) {
                    throw ValidationException::withMessages(["costs.$index.$field" => 'Required for fuel purchases.']);
                }
            }
            $amount = round((float) $cost['liters_required'] * (float) $cost['price_per_liter'], 2);
            if ($this->cents($amount) > 99999999) {
                throw ValidationException::withMessages(["costs.$index.cost_amount" => 'Calculated fuel cost exceeds the supported amount.']);
            }
            $data['fuel_purchases'][] = Arr::only($cost, ['fuel_supplier', 'liters_required', 'price_per_liter', 'remarks']) + ['id' => $fuelId];
            unset($data['costs'][$index]);
        }
        unset($cost);
        $ids = array_filter(array_column($data['fuel_purchases'], 'id'));
        if (count($data['fuel_purchases']) > 200 || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['fuel_purchases' => 'Fuel records must be unique and limited to 200 per trip.']);
        }
    }

    public function financialSummary(Trip $trip): array
    {
        $trip->loadMissing(['personnels', 'fuelPurchases', 'costs']);

        return [
            'Total personnel salary' => $trip->personnels->sum(fn ($person) => $this->cents($person->salary)) / 100,
            'Total fuel cost' => $trip->fuelPurchases->sum(fn ($fuel) => $this->cents($fuel->fuel_price)) / 100,
            'Total additional trip costs' => $trip->costs->sum(fn ($cost) => $this->cents($cost->released_amount) + $this->cents($cost->nonreleased_amount)) / 100,
            'Total released amount' => $trip->total_released,
            'Total non-released amount' => $trip->total_nonreleased,
            'Total trip cost' => $trip->total_cost,
        ];
    }

    private function routeName(Route $route): string
    {
        return implode(', ', array_filter([$route->province, $route->city, $route->brgy]));
    }
}
