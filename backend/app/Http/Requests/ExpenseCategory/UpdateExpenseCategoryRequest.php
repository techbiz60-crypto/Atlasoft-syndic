<?php

namespace App\Http\Requests\ExpenseCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
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
                Rule::unique('expense_categories')
                    ->where('residence_id', $this->user()->residence_id)
                    ->ignore($this->route('expenseCategory')),
            ],
        ];
    }
}
