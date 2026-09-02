<?php

namespace App\Http\Requests;

use App\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only trésorier and conseil accounts are created this way — a
     * residence has exactly one admin (created at registration), and a
     * copropriétaire account is created from the lot it belongs to, not
     * from a generic "add a user" form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', new Enum(Role::class), Rule::in([Role::Tresorier->value, Role::Conseil->value])],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ];
    }
}
