<?php

namespace App\Http\Requests\Lot;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLotRequest extends FormRequest
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
        $buildingId = $this->input('building_id', $this->route('lot')?->building_id);

        return [
            'building_id' => [
                'sometimes',
                'required',
                Rule::exists('buildings', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'lot_type_id' => [
                'sometimes',
                'required',
                Rule::exists('lot_types', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('lots')->where('building_id', $buildingId)->ignore($this->route('lot')),
            ],
            'floor' => ['nullable', 'string', 'max:30'],
            'owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
