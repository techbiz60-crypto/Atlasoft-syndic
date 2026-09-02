<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
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
            'grants' => ['required', 'array'],
            'grants.tresorier' => ['sometimes', 'array'],
            'grants.tresorier.*' => ['string', Rule::exists('permissions', 'key')],
            'grants.conseil' => ['sometimes', 'array'],
            'grants.conseil.*' => ['string', Rule::exists('permissions', 'key')],
            'grants.coproprietaire' => ['sometimes', 'array'],
            'grants.coproprietaire.*' => ['string', Rule::exists('permissions', 'key')],
        ];
    }
}
