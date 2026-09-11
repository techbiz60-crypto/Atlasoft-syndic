<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            // Captured at registration only, for the platform team to spot
            // one person splitting a large residence into several small
            // ones to stay under a cheaper plan's lot ceiling — a signal to
            // review, not an automated block (a legitimate property manager
            // can genuinely run several small residences from one office).
            $table->string('registration_ip', 45)->nullable()->after('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->dropColumn('registration_ip');
        });
    }
};
