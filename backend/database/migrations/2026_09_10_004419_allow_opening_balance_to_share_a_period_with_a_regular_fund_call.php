<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original unique(lot_id, period) predates is_opening_balance and
 * assumed a lot could only ever have one fund call per calendar date — but
 * an opening balance is dated as a reference point, not a real billing
 * month, and nothing stops it from landing on the same date a regular
 * cotisation is later billed for. When that happens, the two need to
 * coexist rather than collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_calls', function (Blueprint $table) {
            // Added before the drop below: MySQL won't drop the old index
            // while it's the only one covering lot_id for its foreign key.
            $table->unique(['lot_id', 'period', 'is_opening_balance']);
            $table->dropUnique(['lot_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('fund_calls', function (Blueprint $table) {
            $table->unique(['lot_id', 'period']);
            $table->dropUnique(['lot_id', 'period', 'is_opening_balance']);
        });
    }
};
