<?php

namespace App\Models;

use App\LotReferenceType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LotReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A simple numbered reference attached to a lot — an elevator access chip
 * or a garage/parking spot number. Both are just "a list of values per
 * lot" with no extra state, so they share this one table instead of two
 * near-identical ones; `type` is what tells them apart.
 */
#[Fillable(['residence_id', 'lot_id', 'type', 'value'])]
class LotReference extends Model
{
    /** @use HasFactory<LotReferenceFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LotReferenceType::class,
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
