<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            // Morocco's Décret 2.23.700 lets the AG fix any 12-month
            // accounting exercise, not necessarily the calendar year —
            // defaults to 1 January, the common case, but a residence can
            // set it to whatever its AG decided instead.
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1)->after('registration_ip');
            $table->unsignedTinyInteger('fiscal_year_start_day')->default(1)->after('fiscal_year_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->dropColumn(['fiscal_year_start_month', 'fiscal_year_start_day']);
        });
    }
};
