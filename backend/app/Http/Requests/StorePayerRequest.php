<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tin')) {
            $this->merge([
                'tin' => strtoupper(trim((string) $this->input('tin'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'payer_type' => ['required', Rule::in(['INDIVIDUAL', 'BUSINESS'])],
            'tin' => ['required', 'string', 'max:50', 'unique:payers,tin'],
            'full_name' => ['required', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'force_create' => ['sometimes', 'boolean'],
            'water_accounts' => ['nullable', 'array'],
            'water_accounts.*.account_no' => ['required_with:water_accounts', 'string', 'max:50', 'distinct', 'unique:water_accounts,account_no'],
            'water_accounts.*.meter_no' => ['required_with:water_accounts', 'string', 'max:50', 'distinct', 'unique:water_accounts,meter_no'],
            'water_accounts.*.tariff_class' => ['required_with:water_accounts', Rule::in(['DOMESTIC', 'COMMERCIAL', 'INSTITUTIONAL'])],
            'water_accounts.*.status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE', 'DISCONNECTED'])],
            'water_accounts.*.location' => ['nullable', 'string', 'max:255'],
            'obligations' => ['nullable', 'array'],
            'obligations.*.revenue_code' => ['required_with:obligations', 'string', 'max:50', 'distinct'],
            'obligations.*.name' => ['required_with:obligations', 'string', 'max:255'],
            'obligations.*.category' => ['nullable', Rule::in(['TAX', 'WATER'])],
        ];
    }
}
