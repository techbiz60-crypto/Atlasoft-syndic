<?php

namespace App\Models;

use App\Role;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'residence_id', 'lot_id', 'whatsapp_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Admin is always allowed everything, no row needed. Every other role
     * only has what's been explicitly granted for their residence via
     * role_permissions — absence of a row means no.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return RolePermission::where('residence_id', $this->residence_id)
            ->where('role', $this->role)
            ->whereHas('permission', fn ($query) => $query->where('key', $key))
            ->exists();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    /**
     * Only meaningful for role=coproprietaire — the apartment this login
     * represents, and (via its building) the scope of what it can see.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_platform_admin' => 'boolean',
        ];
    }
}
