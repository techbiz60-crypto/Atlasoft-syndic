<?php

namespace App\Models;

use App\Role;
use Database\Factories\ResidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'address', 'lots_count', 'bank_rib', 'opening_balance'])]
class Residence extends Model
{
    /** @use HasFactory<ResidenceFactory> */
    use HasFactory;

    /**
     * Financial rights (cotisations/dépenses/recettes) default to granted
     * for the Trésorier role as soon as a residence exists — conseil and
     * copropriétaire start with none, admin never needs any row (always
     * allowed). An admin can revoke/adjust these later from Rôles et
     * permissions.
     */
    protected static function booted(): void
    {
        static::created(function (Residence $residence) {
            $permissionIds = Permission::whereIn('key', ['cotisations.modifier', 'depenses.modifier', 'recettes.modifier'])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                RolePermission::create([
                    'residence_id' => $residence->id,
                    'role' => Role::Tresorier,
                    'permission_id' => $permissionId,
                ]);
            }
        });
    }

    /**
     * Cash actually held just before the given date: what the residence
     * started with, plus every movement that happened before it.
     *
     * opening_balance alone is only the balance at the very beginning —
     * reading it as the opening balance of an arbitrary year silently drops
     * everything collected in the years between. Both the treasury summary
     * and the ledger go through here so they can never drift apart.
     */
    public function cashBalanceBefore(Carbon $date): float
    {
        $sumBefore = fn (string $model, string $dateColumn) => $model::withoutGlobalScopes()
            ->where('residence_id', $this->id)
            ->whereDate($dateColumn, '<', $date)
            ->sum('amount');

        return $this->opening_balance
            + $sumBefore(Payment::class, 'paid_at')
            + $sumBefore(Revenue::class, 'received_at')
            - $sumBefore(Expense::class, 'paid_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
