<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only set for role=coproprietaire — identifies which apartment
            // this login represents, and (via the lot's building) how far
            // its read-only visibility extends. Nullable on delete: if the
            // lot is ever removed, the account survives but loses its scope
            // rather than being silently destroyed.
            $table->foreignId('lot_id')->nullable()->after('residence_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lot_id');
        });
    }
};
