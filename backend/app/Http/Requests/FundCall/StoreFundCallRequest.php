<?php

namespace App\Http\Requests\FundCall;

use App\Models\FundCall;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundCallRequest extends FormRequest
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
            'lot_id' => [
                'required',
                Rule::exists('lots', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'period' => [
                'required',
                'date',
                Rule::unique('fund_calls')->where('lot_id', $this->input('lot_id')),
            ],
            'is_opening_balance' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn () => $this->boolean('is_opening_balance') && $this->lotAlreadyHasOpeningBalance()),
            ],
        ];
    }

    /**
     * A lot only has one meaningful "opening balance" (its total arrears from
     * before it started using the platform) — a second one would just be
     * confusing debt double-counted in Impayés.
     */
    private function lotAlreadyHasOpeningBalance(): bool
    {
        return FundCall::where('lot_id', $this->input('lot_id'))
            ->where('is_opening_balance', true)
            ->exists();
    }
}
