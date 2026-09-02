<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FundCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'lot_id', 'amount', 'period', 'is_opening_balance'])]
class FundCall extends Model
{
    /** @use HasFactory<FundCallFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = ['paid_amount', 'status'];

    protected function casts(): array
    {
        return [
            'is_opening_balance' => 'boolean',
        ];
    }

    /**
     * Stored and compared as a pure date (no time component) — see the
     * identical note on LotTypeRate::effectiveDate() for why this can't be
     * left to the `date` cast or the database column type alone.
     */
    protected function period(): Attribute
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function paidAmount(): Attribute
    {
        return Attribute::get(fn () => $this->payments->sum('amount'));
    }

    protected function status(): Attribute
    {
        return Attribute::get(function () {
            $paid = $this->paid_amount;

            return match (true) {
                $paid <= 0 => 'unpaid',
                $paid < $this->amount => 'partial',
                default => 'paid',
            };
        });
    }
}
