<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['residence_id', 'closed_by_user_id', 'new_admin_user_id', 'closed_at', 'reopened_at', 'reopened_by_user_id', 'reopened_reason'])]
class SyndicMandateClosure extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function newAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_admin_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }
}
