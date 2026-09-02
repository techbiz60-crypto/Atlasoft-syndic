<?php

namespace App\Http\Requests\LotTypeRate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLotTypeRateRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:0'],
            'effective_date' => [
                'required',
                'date',
                Rule::unique('lot_type_rates')->where('lot_type_id', $this->route('lotType')?->id),
            ],
        ];
    }
}
