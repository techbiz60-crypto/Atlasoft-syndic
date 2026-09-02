<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('method');
            $table->date('paid_at');
            $table->string('label')->nullable();
            $table->unsignedInteger('amount');
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
