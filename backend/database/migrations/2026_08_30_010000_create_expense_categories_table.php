<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['residence_id', 'name']);
        });

        // Give every existing residence the standard set of default categories.
        $residenceIds = DB::table('residences')->pluck('id');
        $defaults = ['Eau', 'Électricité', 'Gardiennage', 'Entretien', 'Assurance'];

        foreach ($residenceIds as $residenceId) {
            foreach ($defaults as $name) {
                DB::table('expense_categories')->insert([
                    'residence_id' => $residenceId,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
