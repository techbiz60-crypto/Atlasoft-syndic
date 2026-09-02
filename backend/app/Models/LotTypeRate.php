<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LotTypeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'lot_type_id', 'amount', 'effective_date'])]
class LotTypeRate extends Model
{
    /** @use HasFactory<LotTypeRateFactory> */
    use BelongsToTenant, HasFactory;

    public function lotType(): BelongsTo
    {
        return $this->belongsTo(LotType::class);
    }

    /**
     * Stored and compared as a pure date (no time component). Relying on the
     * database column type alone is not enough: MySQL's DATE columns silently
     * truncate any datetime string on write, but SQLite (used in tests) does
     * not — it stores whatever string Eloquent sends. Without this explicit
     * normalization, uniqueness checks on effective_date pass in production
     * but silently fail in tests (or vice versa).
     */
    protected function effectiveDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }
}
