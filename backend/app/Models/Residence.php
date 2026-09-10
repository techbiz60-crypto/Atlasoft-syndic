<?php

namespace App\Models;

use App\Role;
use Database\Factories\ResidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $appends = ['mandate_lock_boundary'];

    /**
     * When set, the frontend uses this to grey out edit/delete controls on
     * any financial record created before it — kept as a plain ISO string
     * rather than requiring the client to fetch mandate history just to
     * know whether a record is frozen.
     */
    protected function mandateLockBoundary(): Attribute
    {
        return Attribute::get(fn () => $this->currentMandateLock()?->closed_at);
    }

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

    public function generalAssemblies(): HasMany
    {
        return $this->hasMany(GeneralAssembly::class);
    }

    public function mandateClosures(): HasMany
    {
        return $this->hasMany(SyndicMandateClosure::class);
    }

    /**
     * The most recent syndic handover that hasn't been lifted by an Atlasoft
     * support intervention — null means no financial lock is in effect.
     */
    public function currentMandateLock(): ?SyndicMandateClosure
    {
        return $this->mandateClosures()->whereNull('reopened_at')->latest('closed_at')->first();
    }

    /**
     * A financial record (fund call, payment, expense, revenue) created
     * before the last handover is frozen for everyone, including the
     * incoming syndic — the whole point being that neither side can rewrite
     * what the other already declared. Records created afterwards, even for
     * a period that predates the handover (e.g. finally collecting old
     * arrears), are unaffected: this checks when the row itself was
     * written, not the business date it refers to.
     */
    public function isLockedForEditing(Carbon $createdAt): bool
    {
        $lock = $this->currentMandateLock();

        return $lock !== null && $createdAt->lt($lock->closed_at);
    }

    /**
     * The moment exercise $year's books close: the date its AG was held, or
     * January 1st of the following year by default when no AG has been
     * recorded yet — the boundary the AG report always used before this
     * concept existed, kept as the fallback so residences with no AG dates
     * on file behave exactly as before.
     *
     * Read only by the AG report — Trésorerie and the Grand livre are pure
     * cash-basis and must never be affected by when an AG happens.
     */
    public function agCutoffFor(int $year): Carbon
    {
        $heldOn = $this->generalAssemblies->firstWhere('exercise_year', $year)?->held_on;

        return $heldOn ? $heldOn->copy()->startOfDay() : Carbon::create($year + 1, 1, 1)->startOfDay();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
