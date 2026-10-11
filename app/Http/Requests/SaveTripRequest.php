<?php

namespace App\Http\Requests;

use App\Services\TripFormValidation;
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

    public function withValidator($validator): void
    {
        app(TripFormValidation::class)->configureValidator($validator, $this->all(), $this->route('trip'));
    }

    public function messages(): array
    {
        return [
            'personnels.*.employee_id.exists' => 'Select existing Active personnel to assign to a trip.',
            'personnels.required' => TripFormValidation::MESSAGES['personnels'],
            'segments.required' => TripFormValidation::MESSAGES['segments'],
            'costs.required_without' => TripFormValidation::MESSAGES['costs'],
            'costs.*.cost_amount.required_without_all' => TripFormValidation::MESSAGES['costs'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_id' => 'vehicle', 'tariff_id' => 'Trip Rate', 'trip_no' => 'trip number',
            'trip_type' => 'trip type', 'payment_type' => 'payment type', 'drops_number' => 'number of drops',
            'personnels.*.employee_id' => 'employee', 'personnels.*.designation' => 'designation',
            'segments.*.origin_id' => 'route origin', 'segments.*.destination_id' => 'route destination',
            'segments.*.total_distance' => 'route distance',
            'costs.*.cost_code' => 'cost code', 'costs.*.cost_amount' => 'cost amount',
            'costs.*.fuel_supplier' => 'fuel supplier', 'costs.*.liters_required' => 'fuel liters',
            'costs.*.price_per_liter' => 'price per liter', 'costs.*.shipping_line_name' => 'shipping line',
            'costs.*.shipping_rate_id' => 'shipping route',
        ];
    }

    public function rules(): array
    {
        $active = fn ($table, $column = 'id') => Rule::exists($table, $column)->where('is_active', true);
        $amount = 'required|numeric|min:0|max:999999.99|decimal:0,2';

        return [
            'version' => 'required|integer|min:0',
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'tariff_id' => ['required', 'integer', $active('trip_rates')],
            'trip_no' => 'required|string|max:150',
            'trip_type' => ['required', 'string', $active('trip_types', 'trip_type')],
            'payment_type' => ['required', 'string', $active('trip_payments', 'payment_type')],
            'drops_number' => 'required|integer|min:0|max:10000',
            'gcash_number' => 'nullable|string|max:150',
            'personnels' => 'required|array|min:1|max:100',
            'personnels.*.id' => 'nullable|integer|distinct',
            'personnels.*.employee_id' => ['required', 'integer', 'distinct', $active('personnel')],
            'personnels.*.designation' => ['required', 'string', 'max:100', $active('personnel', 'position')],
            'personnels.*.cash_released' => 'prohibited',
            'personnels.*.non_cash_released' => 'prohibited',
            'personnels.*.salary' => 'nullable|numeric|min:0|max:999999.99|decimal:0,2',
            'disbursements' => 'sometimes|array|max:100',
            'disbursements.*.employee_id' => ['required', 'integer', 'distinct', $active('personnel')],
            'disbursements.*.cash_released' => $amount,
            'segments' => 'required|array|min:1|max:200',
            'segments.*.id' => 'nullable|integer|distinct',
            'segments.*.origin_id' => ['required', 'integer', $active('routes')],
            'segments.*.destination_id' => ['required', 'integer', $active('routes')],
            'segments.*.total_distance' => $amount,
            'fuel_purchases' => 'present|array|max:200',
            'fuel_purchases.*.id' => 'nullable|integer|distinct',
            'fuel_purchases.*.fuel_supplier' => ['required', 'integer', $active('fuel_suppliers')],
            'fuel_purchases.*.liters_required' => $amount,
            'fuel_purchases.*.price_per_liter' => $amount,
            'fuel_purchases.*.remarks' => 'nullable|string|max:2000',
            'costs' => 'required_without:fuel_purchases|array|max:200',
            'costs.*.id' => 'nullable|integer|distinct',
            'costs.*.fuel_purchase_id' => 'nullable|integer|distinct',
            'costs.*.costgroup' => 'nullable|string|max:100',
            'costs.*.cost_code' => ['nullable', 'required_without:costs.*.fuel_purchase_id', 'integer', $active('other_costs')],
            'costs.*.shipping_rate_id' => ['nullable', 'integer', $active('shipping_rates')],
            'costs.*.shipping_line_name' => ['nullable', 'string', $active('shipping_rates', 'shipping_line_name')],
            'costs.*.cost_amount' => 'required_without_all:costs.*.released_amount,costs.*.nonreleased_amount|numeric|min:0|max:999999.99|decimal:0,2',
            'costs.*.liters_required' => 'nullable|numeric|min:0|max:999999.99|decimal:0,2',
            'costs.*.price_per_liter' => 'nullable|numeric|min:0|max:999999.99|decimal:0,2',
            'costs.*.fuel_supplier' => ['nullable', 'integer', $active('fuel_suppliers')],
            'costs.*.remarks' => 'nullable|string|max:2000',
            'costs.*.is_cash_released' => 'sometimes|boolean',
            'costs.*.released_amount' => 'sometimes|'.$amount,
            'costs.*.nonreleased_amount' => 'sometimes|'.$amount,
            'costs.*.reference_id' => 'nullable|string|max:150',
        ];
    }
}
