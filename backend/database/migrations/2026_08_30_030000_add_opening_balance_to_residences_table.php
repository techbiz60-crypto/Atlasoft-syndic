<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->integer('opening_balance')->default(0)->after('bank_rib');
        });
    }

    public function down(): void
    {
        Schema::table('residences', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
