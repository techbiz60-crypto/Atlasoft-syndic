<?php

namespace App\Http\Requests\Payment;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
