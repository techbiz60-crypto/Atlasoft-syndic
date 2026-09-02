<?php

namespace App\Http\Requests\Platform;

use App\BillingCycle;
use App\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ActivateSubscriptionRequest extends FormRequest
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
            'cycle' => ['required', new Enum(BillingCycle::class)],
            'plan' => ['sometimes', 'nullable', new Enum(SubscriptionPlan::class)],
            'amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
