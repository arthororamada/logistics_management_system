<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTripRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['personnels', 'segments', 'fuel_purchases', 'costs'] as $collection) {
            if (! $this->has($collection)) {
                $this->merge([$collection => []]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('trips.update');
    }

    public function rules(): array
    {
        $active = fn ($table, $column = 'id') => Rule::exists($table, $column)->where('is_active', true);
        $amount = 'required|numeric|min:0|max:999999.99|decimal:0,2';

        return [
            'version' => 'required|integer|min:0',
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'tariff_id' => ['nullable', 'integer', $active('trip_rates')],
            'trip_no' => 'nullable|string|max:150',
            'trip_type' => ['nullable', 'string', $active('trip_types', 'trip_type')],
            'payment_type' => ['nullable', 'string', $active('trip_payments', 'payment_type')],
            'drops_number' => 'required|integer|min:0|max:10000',
            'gcash_number' => 'nullable|string|max:150',
            'personnels' => 'present|array|max:100',
            'personnels.*.id' => 'nullable|integer|distinct',
            'personnels.*.employee_id' => ['required', 'integer', 'distinct', $active('personnel')],
            'personnels.*.designation' => ['required', 'string', 'max:100', $active('personnel', 'position')],
            'personnels.*.cash_released' => 'prohibited',
            'personnels.*.non_cash_released' => 'prohibited',
            'personnels.*.salary' => 'nullable|numeric|min:0|max:999999.99|decimal:0,2',
            'disbursements' => 'sometimes|array|max:100',
            'disbursements.*.employee_id' => ['required', 'integer', 'distinct', $active('personnel')],
            'disbursements.*.cash_released' => $amount,
            'segments' => 'present|array|max:200',
            'segments.*.id' => 'nullable|integer|distinct',
            'segments.*.origin_id' => ['required', 'integer', $active('routes')],
            'segments.*.destination_id' => ['required', 'integer', $active('routes')],
            'segments.*.total_distance' => $amount,
            'fuel_purchases' => 'present|array|max:200',
            'fuel_purchases.*.id' => 'nullable|integer|distinct',
            'fuel_purchases.*.fuel_supplier' => ['required', 'integer', $active('fuel_suppliers')],
            'fuel_purchases.*.liters_required' => $amount,
            'fuel_purchases.*.price_per_liter' => $amount,
            'costs' => 'present|array|max:200',
            'costs.*.id' => 'nullable|integer|distinct',
            'costs.*.costgroup' => 'nullable|string|max:100',
            'costs.*.cost_code' => ['required', 'integer', $active('other_costs')],
            'costs.*.shipping_rate_id' => ['nullable', 'integer', $active('shipping_rates')],
            'costs.*.remarks' => 'nullable|string|max:2000',
            'costs.*.is_cash_released' => 'required|boolean',
            'costs.*.released_amount' => $amount,
            'costs.*.nonreleased_amount' => $amount,
            'costs.*.reference_id' => 'nullable|string|max:150',
        ];
    }
}
