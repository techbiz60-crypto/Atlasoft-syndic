<?php

namespace App\Models\Concerns;

use App\Models\Residence;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->residence_id) && Auth::check()) {
                $model->residence_id = Auth::user()->residence_id;
            }
        });
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}
