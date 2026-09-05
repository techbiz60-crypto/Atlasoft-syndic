<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Second line of defence behind the controller guards: the database itself
 * now refuses to delete a record another one still points at, so no future
 * code path (or manual SQL) can silently wipe a chain of apartments, fund
 * calls and payments.
 *
 * Deliberately NOT restricted:
 * - every residence_id key, so deleting a whole residence (tenant
 *   offboarding) still cleans up after itself;
 * - lot_owners, lot_references and lot_type_rates, which are always created
 *   alongside their parent — restricting them would make every apartment
 *   and lot type permanently undeletable.
 *
 * Note for whoever adds residence deletion later: with fund_calls.lot_id
 * restricted, the residence cascade can no longer remove lots on its own —
 * children must be deleted explicitly, oldest-dependency first.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, on: string}>
     */
    private array $keys = [
        ['table' => 'lots', 'column' => 'building_id', 'on' => 'buildings'],
        ['table' => 'lots', 'column' => 'lot_type_id', 'on' => 'lot_types'],
        ['table' => 'fund_calls', 'column' => 'lot_id', 'on' => 'lots'],
        ['table' => 'payments', 'column' => 'fund_call_id', 'on' => 'fund_calls'],
        ['table' => 'expenses', 'column' => 'expense_category_id', 'on' => 'expense_categories'],
        ['table' => 'revenues', 'column' => 'revenue_category_id', 'on' => 'revenue_categories'],
    ];

    public function up(): void
    {
        // SQLite (used by the test suite) can't alter foreign keys in place;
        // the controller guards cover behaviour there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->keys as $key) {
            Schema::table($key['table'], function (Blueprint $table) use ($key) {
                $table->dropForeign([$key['column']]);
                $table->foreign($key['column'])->references('id')->on($key['on'])->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->keys as $key) {
            Schema::table($key['table'], function (Blueprint $table) use ($key) {
                $table->dropForeign([$key['column']]);
                $table->foreign($key['column'])->references('id')->on($key['on'])->cascadeOnDelete();
            });
        }
    }
};
