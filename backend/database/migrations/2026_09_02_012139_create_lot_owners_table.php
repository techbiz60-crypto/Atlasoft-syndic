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
        Schema::create('lot_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('owner_name');
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable();
            $table->date('started_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lot_owners');
    }
};
