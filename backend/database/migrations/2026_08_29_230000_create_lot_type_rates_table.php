<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_type_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['lot_type_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_type_rates');
    }
};
