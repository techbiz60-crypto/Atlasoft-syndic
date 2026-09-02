<?php

namespace App\Http\Requests\Lot;

use App\LotReferenceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreLotReferenceRequest extends FormRequest
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
            'type' => ['required', new Enum(LotReferenceType::class)],
            'value' => [
                'required',
                'string',
                'max:50',
                Rule::unique('lot_references')->where('lot_id', $this->route('lot')?->id)->where('type', $this->input('type')),
            ],
        ];
    }
}
