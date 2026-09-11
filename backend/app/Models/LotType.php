<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LotTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'residence_id'])]
class LotType extends Model
{
    /** @use HasFactory<LotTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = ['current_amount'];

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(LotTypeRate::class)->orderByDesc('effective_date');
    }

    protected function currentAmount(): Attribute
    {
        return Attribute::get(fn () => $this->rateAt(Carbon::now())?->amount);
    }

    /**
     * The rate in force on a given date — or, if that date predates every
     * rate ever recorded for this type (e.g. estimating arrears for months
     * before a brand-new lot type's first rate was entered), the earliest
     * one on file. Without this fallback, a lot type created mid-year (its
     * only rate dated that month) silently has no rate for every earlier
     * month, so Impayés/AG recap/dashboard dues all undercount arrears
     * down to just the months since that rate started — the exact
     * complaint that surfaced this: a residence onboarded in September
     * showing "1 month owed" instead of 9.
     */
    public function rateAt(Carbon $date): ?LotTypeRate
    {
        return $this->rates->first(fn (LotTypeRate $rate) => $rate->effective_date->lte($date))
            ?? $this->rates->last();
    }
}
