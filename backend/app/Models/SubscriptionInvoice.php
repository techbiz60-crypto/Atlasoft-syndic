<?php

namespace App\Models;

use App\BillingCycle;
use App\Models\Concerns\BelongsToTenant;
use App\SubscriptionPlan;
use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'subscription_id', 'plan', 'billing_cycle', 'amount', 'period_start', 'period_end', 'validated_at'])]
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = ['plan_label'];

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'billing_cycle' => BillingCycle::class,
            'validated_at' => 'datetime',
        ];
    }

    protected function planLabel(): Attribute
    {
        return Attribute::get(fn () => $this->plan->label());
    }

    /**
     * Stored and compared as a pure date — see the identical note on
     * LotTypeRate::effectiveDate() for why this can't be left to a plain
     * `date` cast or the database column type alone.
     */
    protected function periodStart(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function periodEnd(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}
