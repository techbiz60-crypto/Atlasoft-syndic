<?php

namespace App\Http\Requests\Lot;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreLotOwnerRequest extends FormRequest
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
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'started_at' => ['required', 'date'],
        ];
    }

    /**
     * A new owner's start date must come after the current owner's — the
     * history list is sorted by this date, so an earlier one would silently
     * flip who displays as "current" and who displays as "past" (this
     * happened for real: a lot's new owner was mistakenly backdated before
     * the previous one, making the old owner look current).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lot = $this->route('lot');
            $latestOwner = $lot?->owners()->first();

            if (! $latestOwner || ! $this->filled('started_at')) {
                return;
            }

            if (Carbon::parse($this->input('started_at'))->lt($latestOwner->started_at)) {
                $validator->errors()->add(
                    'started_at',
                    "Cette date doit être postérieure au {$latestOwner->started_at->format('d/m/Y')}, date depuis laquelle {$latestOwner->owner_name} est propriétaire.",
                );
            }
        });
    }
}
