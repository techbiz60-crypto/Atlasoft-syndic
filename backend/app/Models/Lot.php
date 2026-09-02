<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['residence_id', 'building_id', 'lot_type_id', 'number', 'floor', 'owner_name', 'owner_phone', 'owner_email'])]
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Every lot starts its ownership trail as soon as it exists, so
     * `owners()` never comes back empty — the very first row just mirrors
     * whatever owner_* fields the lot was created with.
     */
    protected static function booted(): void
    {
        static::created(function (Lot $lot) {
            $lot->owners()->create([
                'residence_id' => $lot->residence_id,
                'owner_name' => $lot->owner_name,
                'owner_phone' => $lot->owner_phone,
                'owner_email' => $lot->owner_email,
                'started_at' => now(),
            ]);
        });
    }

    public function lotType(): BelongsTo
    {
        return $this->belongsTo(LotType::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function fundCalls(): HasMany
    {
        return $this->hasMany(FundCall::class);
    }

    public function openingBalance(): HasOne
    {
        return $this->hasOne(FundCall::class)->where('is_opening_balance', true);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(LotOwner::class)->orderByDesc('started_at');
    }

    /**
     * The resident login for this apartment's current owner, if one has
     * been granted — null means no one has been given access yet.
     */
    public function residentUser(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(LotReference::class)->orderBy('value');
    }
}
