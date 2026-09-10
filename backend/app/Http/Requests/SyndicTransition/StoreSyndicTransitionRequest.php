<?php

namespace App\Http\Requests\SyndicTransition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSyndicTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Typing the residence's exact name (rather than a fixed phrase) works
     * in both FR and AR without translating a magic string, and confirms
     * the admin has the right residence in mind — the same pattern used by
     * "type the repo name to delete it" confirmations elsewhere.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation_text' => ['required', 'string', Rule::in([$this->user()->residence->name])],
            'password' => ['required', 'string', 'current_password'],
            'new_admin_name' => ['required', 'string', 'max:255'],
            'new_admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation_text.in' => 'Le texte saisi ne correspond pas exactement au nom de la résidence.',
            'password.current_password' => 'Mot de passe incorrect.',
        ];
    }
}
