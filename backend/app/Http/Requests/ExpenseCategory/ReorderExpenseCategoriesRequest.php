<?php

namespace App\Http\Requests\ExpenseCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderExpenseCategoriesRequest extends FormRequest
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
            'ids' => ['required', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('expense_categories', 'id')->where('residence_id', $this->user()->residence_id),
            ],
        ];
    }
}
