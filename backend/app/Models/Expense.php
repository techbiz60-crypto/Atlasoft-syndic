<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\PaymentMethod;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['residence_id', 'expense_category_id', 'method', 'paid_at', 'label', 'amount', 'receipt_path'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
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
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
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

    protected function hasReceipt(): Attribute
    {
        return Attribute::get(fn () => ! empty($this->receipt_path));
    }
}
