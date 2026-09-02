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
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('billing_cycle');
            $table->unsignedInteger('amount');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('validated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
