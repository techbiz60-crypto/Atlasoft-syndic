<?php

namespace App\Http\Requests\Building;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingRequest extends FormRequest
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
                Rule::unique('buildings')->where('residence_id', $this->user()->residence_id)->ignore($this->route('building')),
            ],
        ];
    }
}
