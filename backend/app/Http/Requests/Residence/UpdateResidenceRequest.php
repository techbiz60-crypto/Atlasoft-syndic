<?php

namespace App\Http\Requests\Residence;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateResidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'lots_count' => ['sometimes', 'required', 'integer', 'min:1'],
            'bank_rib' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['sometimes', 'required', 'integer'],
            // The AG can fix the accounting exercise to start on any month
            // (Décret 2.23.700) — 1 January stays the default so a
            // residence that never touches this behaves exactly as before.
            'fiscal_year_start_month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'fiscal_year_start_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
        ];
    }
}
