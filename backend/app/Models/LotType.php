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

    public function rateAt(Carbon $date): ?LotTypeRate
    {
        return $this->rates->first(fn (LotTypeRate $rate) => $rate->effective_date->lte($date));
    }
}
