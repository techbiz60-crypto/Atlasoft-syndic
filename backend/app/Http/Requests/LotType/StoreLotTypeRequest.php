<?php

namespace App\Http\Requests\LotType;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLotTypeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('lot_types')->where('residence_id', $this->user()->residence_id),
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
