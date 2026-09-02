<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'fund_call_id', 'batch_id', 'amount', 'paid_at', 'method', 'notes', 'owner_name'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Freezes who owned the lot at the moment of payment, so a later change
     * of ownership doesn't rewrite history — the Paiements list and receipts
     * always show who actually paid, not whoever owns the lot today.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->owner_name) && $payment->fund_call_id) {
                $payment->owner_name = FundCall::withoutGlobalScopes()->find($payment->fund_call_id)?->lot?->owner_name;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * Stored and compared as a pure date (no time component) — see the
     * identical note on LotTypeRate::effectiveDate() for why this can't be
     * left to the `date` cast or the database column type alone.
     */
    protected function paidAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function fundCall(): BelongsTo
    {
        return $this->belongsTo(FundCall::class);
    }
}
