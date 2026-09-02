<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['residence_id', 'name']);
        });

        // Give every existing residence a starting set of default revenue categories.
        $residenceIds = DB::table('residences')->pluck('id');
        $defaults = ['Vente de biens/services', 'Location', 'Pénalités de retard', 'Divers'];

        foreach ($residenceIds as $residenceId) {
            foreach ($defaults as $name) {
                DB::table('revenue_categories')->insert([
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
        Schema::dropIfExists('revenue_categories');
    }
};
