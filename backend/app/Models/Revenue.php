<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\PaymentMethod;
use Database\Factories\RevenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'revenue_category_id', 'method', 'received_at', 'label', 'amount', 'receipt_path'])]
class Revenue extends Model
{
    /** @use HasFactory<RevenueFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = ['has_receipt'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RevenueCategory::class, 'revenue_category_id');
    }

    /**
     * Stored and compared as a pure date (no time component) — see the
     * identical note on LotTypeRate::effectiveDate() for why this can't be
     * left to the `date` cast or the database column type alone.
     */
    protected function receivedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    protected function hasReceipt(): Attribute
    {
        return Attribute::get(fn () => ! empty($this->receipt_path));
    }
}
