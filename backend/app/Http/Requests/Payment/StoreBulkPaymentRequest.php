<?php

namespace App\Http\Requests\Payment;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBulkPaymentRequest extends FormRequest
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
            'amount' => ['required_without:months', 'nullable', 'integer', 'min:1'],
            'months' => ['sometimes', 'array', 'min:1'],
            'months.*' => ['integer', 'between:1,12', 'distinct'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
