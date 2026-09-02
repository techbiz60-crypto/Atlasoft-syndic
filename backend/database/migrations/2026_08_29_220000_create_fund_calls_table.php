<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('period');
            $table->timestamps();

            $table->unique(['lot_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_calls');
    }
};
