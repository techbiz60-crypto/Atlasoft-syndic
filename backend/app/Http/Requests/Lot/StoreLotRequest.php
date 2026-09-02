<?php

namespace App\Http\Requests\Lot;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLotRequest extends FormRequest
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
            'building_id' => [
                'required',
                Rule::exists('buildings', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'lot_type_id' => [
                'required',
                Rule::exists('lot_types', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('lots')->where('building_id', $this->integer('building_id')),
            ],
            'floor' => ['nullable', 'string', 'max:30'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
