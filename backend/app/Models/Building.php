<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'residence_id'])]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use BelongsToTenant, HasFactory;

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
