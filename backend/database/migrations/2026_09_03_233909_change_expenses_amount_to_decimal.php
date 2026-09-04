<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * doctrine/dbal (needed for Blueprint::change()) isn't installed, so the
     * column type is swapped via add → copy → drop instead of an in-place
     * change — this also keeps it portable between MySQL (prod) and SQLite
     * (tests), and preserves existing amounts.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_decimal', 10, 2)->unsigned()->default(0)->after('amount');
        });

        DB::table('expenses')->update(['amount_decimal' => DB::raw('amount')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->unsigned()->default(0)->after('method');
        });

        DB::table('expenses')->update(['amount' => DB::raw('amount_decimal')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount_decimal');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('amount_int')->default(0)->after('amount');
        });

        DB::table('expenses')->update(['amount_int' => DB::raw('ROUND(amount)')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('amount')->default(0)->after('method');
        });

        DB::table('expenses')->update(['amount' => DB::raw('amount_int')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount_int');
        });
    }
};
