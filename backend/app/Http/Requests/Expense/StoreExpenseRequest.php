<?php

namespace App\Http\Requests\Expense;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExpenseRequest extends FormRequest
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
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
