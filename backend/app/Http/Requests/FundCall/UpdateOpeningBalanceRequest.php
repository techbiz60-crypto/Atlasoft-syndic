<?php

namespace App\Http\Requests\FundCall;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpeningBalanceRequest extends FormRequest
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
            'period' => ['required', 'date'],
        ];
    }
}
