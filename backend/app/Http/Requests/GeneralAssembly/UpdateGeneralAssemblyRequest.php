<?php

namespace App\Http\Requests\GeneralAssembly;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralAssemblyRequest extends FormRequest
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
            'held_on' => ['required', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'agenda' => ['sometimes', 'nullable', 'array'],
            'agenda.*' => ['string', 'max:500'],
            'convocation_sent_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
