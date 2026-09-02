<?php

namespace App\Http\Requests\RevenueCategory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRevenueCategoryRequest extends FormRequest
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
                Rule::unique('revenue_categories')
                    ->where('residence_id', $this->user()->residence_id)
                    ->ignore($this->route('revenueCategory')),
            ],
        ];
    }
}
