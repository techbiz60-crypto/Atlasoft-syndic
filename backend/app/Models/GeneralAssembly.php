<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GeneralAssemblyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The date one exercise's AG was (or is planned to be) held.
 *
 * Once that date passes, the exercise's books are closed: late collections
 * of its own dues, from that date on, belong to whichever exercise is
 * current at the time they're actually paid — reported as debt recovered
 * from a prior exercise, not folded back into the closed one. See
 * Residence::agCutoffFor(), the only place this is read.
 */
#[Fillable(['exercise_year', 'held_on'])]
class GeneralAssembly extends Model
{
    /** @use HasFactory<GeneralAssemblyFactory> */
    use BelongsToTenant, HasFactory;

    protected function heldOn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }
}
