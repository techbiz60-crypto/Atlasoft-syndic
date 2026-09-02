<?php

namespace App\Http\Requests\FundCall;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MatrixRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
        ];
    }
}
