<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RevenueCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['residence_id', 'name'])]
class RevenueCategory extends Model
{
    /** @use HasFactory<RevenueCategoryFactory> */
    use BelongsToTenant, HasFactory;

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }
}
