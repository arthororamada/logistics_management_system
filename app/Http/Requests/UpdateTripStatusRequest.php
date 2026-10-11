<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('trip-status-updaters.update');
    }

    protected function prepareForValidation(): void
    {
        foreach (['trip_id', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'trip_id' => 'required|string|max:50',
            'version' => 'required|integer|min:0',
            'new_status' => ['required', Rule::in(['Open', 'Cancelled'])],
            'reason' => 'required|string|max:2000',
        ];
    }
}
