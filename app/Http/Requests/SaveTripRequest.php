<?php

namespace App\Http\Requests;

use App\Models\Personnel;
use App\Services\TripFormValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTripRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['personnels', 'segments', 'fuel_purchases', 'costs', 'waybills'] as $collection) {
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
        $designations = Personnel::where('is_active', true)->get(['position', 'personnel_type'])
            ->map(fn ($person) => mb_strtolower(trim(filled($person->position) ? $person->position : $person->personnel_type)))->all();

        return [
            'waybills' => 'required|array|min:1|max:2000',
            'waybills.*.id' => 'nullable|integer|distinct',
            'waybills.*.dm_id' => 'required|integer|min:1|max:2147483647',
            'waybills.*.waybill_number' => 'required|string|max:255|distinct:strict',
            'waybills.*.quantity' => 'required|integer|min:0|max:2147483647',
            'waybills.*.kilogram' => 'required|integer|min:0|max:2147483647',
            'waybills.*.cbm' => 'required|numeric|min:0|max:999999999',
            'waybills.*.drops_number' => 'required|integer|min:0|max:2147483647',
            'version' => 'required|integer|min:0',
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'tariff_id' => ['required', 'integer', $active('trip_rates')],
            'trip_no' => 'required|string|max:50',
            'trip_type' => ['required', 'string', $active('trip_types', 'trip_type')],
            'payment_type' => ['required', 'string', $active('trip_payments', 'payment_type')],
            'drops_number' => 'required|integer|min:0|max:10000',
            'gcash_number' => 'nullable|string|max:150',
            'personnels' => 'required|array|min:1|max:100',
            'personnels.*.id' => 'nullable|integer|distinct',
            'personnels.*.employee_id' => ['required', 'integer', 'distinct', $active('personnel')],
            'personnels.*.designation' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($designations) {
                if (! is_string($value) || ! in_array(mb_strtolower(trim($value)), $designations, true)) {
                    $fail('Select a designation configured for Active personnel.');
                }
            }],
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
