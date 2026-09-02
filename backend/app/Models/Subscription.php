<?php

namespace App\Models;

use App\BillingCycle;
use App\Models\Concerns\BelongsToTenant;
use App\SubscriptionPlan;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'plan', 'billing_cycle', 'trial_ends_at', 'current_period_end'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = ['status', 'is_writable', 'days_remaining', 'plan_label', 'monthly_price', 'annual_price'];

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'billing_cycle' => BillingCycle::class,
        ];
    }

    /**
     * Stored and compared as a pure date — see the identical note on
     * LotTypeRate::effectiveDate() for why this can't be left to a plain
     * `date` cast or the database column type alone.
     */
    protected function trialEndsAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    protected function currentPeriodEnd(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * The free plan never expires. A paid plan is writable while its trial
     * or its current billing period hasn't ended yet.
     */
    protected function isWritable(): Attribute
    {
        return Attribute::get(function () {
            if ($this->plan === SubscriptionPlan::Free) {
                return true;
            }

            if ($this->current_period_end) {
                return $this->current_period_end->isFuture();
            }

            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        });
    }

    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->plan === SubscriptionPlan::Free) {
                return 'free';
            }

            if ($this->current_period_end) {
                return $this->current_period_end->isFuture() ? 'active' : 'expired';
            }

            return $this->trial_ends_at?->isFuture() ? 'trial' : 'expired';
        });
    }

    protected function daysRemaining(): Attribute
    {
        return Attribute::get(function () {
            $endDate = $this->current_period_end ?? $this->trial_ends_at;

            return $endDate ? Carbon::now()->startOfDay()->diffInDays($endDate, false) : null;
        });
    }

    protected function planLabel(): Attribute
    {
        return Attribute::get(fn () => $this->plan->label());
    }

    protected function monthlyPrice(): Attribute
    {
        return Attribute::get(fn () => $this->plan->monthlyPrice());
    }

    protected function annualPrice(): Attribute
    {
        return Attribute::get(fn () => $this->plan->annualPrice());
    }
}
