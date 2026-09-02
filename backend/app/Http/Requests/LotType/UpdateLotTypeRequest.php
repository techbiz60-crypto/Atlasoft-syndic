<?php

namespace App\Http\Requests\LotType;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLotTypeRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('lot_types')->where('residence_id', $this->user()->residence_id)->ignore($this->route('lotType')),
            ],
        ];
    }
}
