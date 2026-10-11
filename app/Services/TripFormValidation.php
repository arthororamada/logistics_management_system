<?php

namespace App\Services;

use App\Models\OtherCost;
use App\Models\Trip;
use Illuminate\Validation\Validator;

class TripFormValidation
{
    public const MESSAGES = [
        'general' => 'General Trip Information is required. Please complete all required fields.',
        'vehicle' => 'Vehicle is required. Please select a vehicle.',
        'segments' => 'Route is required. Please select or configure a valid route with a positive distance.',
        'personnels' => 'Personnel is required. Please assign the required personnel, including a driver.',
        'costs' => 'Additional Trip Cost is required.',
        'disbursements' => 'Please correct the invalid fields in Disbursement Information.',
    ];

    public static function section(string $field): string
    {
        $root = explode('.', $field)[0];

        return match ($root) {
            'vehicle_id' => 'vehicle',
            'segments', 'trip_route_origin', 'trip_route_destination', 'total_distance' => 'segments',
            'personnels' => 'personnels',
            'costs', 'fuel_purchases', 'fuelPurchases' => 'costs',
            'disbursements', 'gcash_number' => 'disbursements',
            default => 'general',
        };
    }

    public static function sections(array $errors): array
    {
        $affected = array_unique(array_map(self::section(...), array_keys($errors)));

        return array_intersect_key(self::MESSAGES, array_flip($affected));
    }

    public function configureValidator(Validator $validator, array $data, Trip $trip): void
    {
        $validator->after(function ($validator) use ($data, $trip) {
            $people = is_array($data['personnels'] ?? null) ? $data['personnels'] : [];
            if (! $validator->errors()->has('personnels') && ! collect($people)->contains(fn ($row) => is_array($row) && is_string($row['designation'] ?? null) && strtolower($row['designation']) === 'driver')) {
                $validator->errors()->add('personnels', self::MESSAGES['personnels']);
            }
            $segments = is_array($data['segments'] ?? null) ? $data['segments'] : [];
            $distance = collect($segments)->sum(fn ($row) => is_array($row) && is_numeric($row['total_distance'] ?? null) ? (float) $row['total_distance'] : 0);
            if ($distance <= 0 && ! $validator->errors()->has('segments')) {
                $validator->errors()->add('segments', self::MESSAGES['segments']);
            }
            // Validate the required details for each supplied cost type.
            $costs = is_array($data['costs'] ?? null) ? $data['costs'] : [];
            foreach ($costs as $index => $cost) {
                if (! is_array($cost)) continue;
                $code = is_scalar($cost['cost_code'] ?? null) ? OtherCost::find($cost['cost_code']) : null;
                $type = strtoupper(trim($code?->cost_code ?? (! empty($cost['fuel_purchase_id']) ? 'FUEL_PURCHASE' : '')));
                $required = match ($type) {
                    'FUEL_PURCHASE' => ['fuel_supplier', 'liters_required', 'price_per_liter'],
                    'CASH_FUEL' => ['liters_required', 'price_per_liter'],
                    'RORO_COST' => ['shipping_line_name', 'shipping_rate_id'],
                    default => [],
                };
                if (! array_key_exists('cost_amount', $cost) && ! isset($cost['released_amount'], $cost['nonreleased_amount']) && ! in_array($type, ['FUEL_PURCHASE', 'CASH_FUEL', 'RORO_COST'])) {
                    $required[] = 'cost_amount';
                }
                foreach ($required as $field) {
                    if (! isset($cost[$field]) || $cost[$field] === '') {
                        $validator->errors()->add("costs.$index.$field", $field === 'cost_amount' ? self::MESSAGES['costs'] : 'Complete this required cost field.');
                    }
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                // Include eligibility in an incomplete form's full error summary.
                // A valid submission is checked again under resource locks by TripService.
                $availability = app(TripAssignmentEligibility::class)->availability($trip);
                $vehicleId = $data['vehicle_id'] ?? null;
                if (is_scalar($vehicleId) && ! $validator->errors()->has('vehicle_id') && ($reason = $availability['vehicles'][$vehicleId] ?? null)) {
                    $validator->errors()->add('vehicle_id', $reason);
                }
                foreach ($people as $index => $person) {
                    $id = is_array($person) ? ($person['employee_id'] ?? null) : null;
                    $field = "personnels.$index.employee_id";
                    if (is_scalar($id) && ! $validator->errors()->has($field) && ($reason = $availability['personnels'][$id] ?? null)) {
                        $validator->errors()->add($field, $reason);
                    }
                }
            }
        });
    }
}
