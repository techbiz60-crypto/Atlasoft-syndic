<?php

namespace App\Http\Requests\Payment;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePaymentBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Editing a grouped payment fully replaces it: the admin re-picks which
     * months it covers (each settled in full at the current rate) plus the
     * shared date/method/note — same shape as the "select months" bulk
     * payment flow, since under the hood this is delete-and-recreate.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['integer', 'between:1,12', 'distinct'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
