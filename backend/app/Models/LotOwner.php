<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LotOwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per owner a lot has ever had, ordered by `started_at` — the most
 * recent row is the current owner. Kept alongside (not instead of) the
 * denormalized owner_* fields on Lot itself, so every existing screen that
 * already reads `lot->owner_name` keeps working unchanged; this table only
 * adds the "who owned it before, and since when" trail.
 */
#[Fillable(['residence_id', 'lot_id', 'owner_name', 'owner_phone', 'owner_email', 'started_at'])]
class LotOwner extends Model
{
    /** @use HasFactory<LotOwnerFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Stored and compared as a pure date — see the identical note on
     * LotTypeRate::effectiveDate() for why this can't be left to the `date`
     * cast or the database column type alone.
     */
    protected function startedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
