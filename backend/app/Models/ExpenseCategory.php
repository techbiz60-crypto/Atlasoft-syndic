<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['residence_id', 'name'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use BelongsToTenant, HasFactory;

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
