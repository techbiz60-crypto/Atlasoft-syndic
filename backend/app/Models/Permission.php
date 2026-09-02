<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The fixed, code-defined catalog of grantable rights (seeded by its
 * migration) — not residence-editable. What IS residence-editable is which
 * role gets which of these, tracked in role_permissions.
 */
class Permission extends Model
{
    protected $fillable = [];
}
