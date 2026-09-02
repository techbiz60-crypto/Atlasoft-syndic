<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Role;
use Database\Factories\RolePermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = this role, in this residence, has been granted this permission.
 * Absence of a row means the role does NOT have it — there's no separate
 * "explicitly denied" state, keeping the model simple (grant-only).
 */
#[Fillable(['residence_id', 'role', 'permission_id'])]
class RolePermission extends Model
{
    /** @use HasFactory<RolePermissionFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
