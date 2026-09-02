<?php

namespace App\Http\Requests\Lot;

use App\Models\Lot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreLotsRequest extends FormRequest
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
            'building_id' => [
                'required',
                Rule::exists('buildings', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'lots' => ['required', 'array', 'min:1'],
            'lots.*.number' => ['required', 'string', 'max:50'],
            'lots.*.floor' => ['nullable', 'string', 'max:30'],
            'lots.*.lot_type_id' => [
                'required',
                Rule::exists('lot_types', 'id')->where('residence_id', $this->user()->residence_id),
            ],
            'lots.*.owner_name' => ['required', 'string', 'max:255'],
            'lots.*.owner_phone' => ['nullable', 'string', 'max:30'],
            'lots.*.owner_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Every row's number must be unique within the batch and not already
     * used in the target building — checked here (rather than a `Rule`)
     * since it needs to compare rows against each other, not just the DB.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('lots', []);
            $buildingId = $this->input('building_id');

            $existingNumbers = Lot::where('building_id', $buildingId)
                ->pluck('number')
                ->map(fn (string $number) => mb_strtolower($number))
                ->all();

            $seen = [];

            foreach ($rows as $index => $row) {
                $number = mb_strtolower((string) ($row['number'] ?? ''));

                if ($number === '') {
                    continue;
                }

                if (in_array($number, $existingNumbers, true)) {
                    $validator->errors()->add("lots.{$index}.number", "Le lot \"{$row['number']}\" existe déjà dans cet immeuble.");
                } elseif (isset($seen[$number])) {
                    $validator->errors()->add("lots.{$index}.number", "Le lot \"{$row['number']}\" est en double dans cette liste.");
                }

                $seen[$number] = true;
            }
        });
    }
}
