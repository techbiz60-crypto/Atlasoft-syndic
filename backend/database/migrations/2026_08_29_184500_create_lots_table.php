<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_type_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('owner_name');
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable();
            $table->timestamps();

            $table->unique(['residence_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
