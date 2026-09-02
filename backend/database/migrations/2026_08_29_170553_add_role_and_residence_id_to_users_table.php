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
            $table->foreignId('residence_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->after('residence_id')->default('admin');
            $table->string('whatsapp_number')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residence_id');
            $table->dropColumn(['role', 'whatsapp_number']);
        });
    }
};
