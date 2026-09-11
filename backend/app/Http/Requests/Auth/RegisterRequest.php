<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'residence_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'lots_count' => ['required', 'integer', 'min:1'],
            // Whatever the residence held in cash before switching to the
            // platform — 0 is a legitimate answer for a brand-new syndic,
            // so this stays required rather than defaulting silently.
            'opening_balance' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'string', 'in:fr,ar'],
        ];
    }
}
