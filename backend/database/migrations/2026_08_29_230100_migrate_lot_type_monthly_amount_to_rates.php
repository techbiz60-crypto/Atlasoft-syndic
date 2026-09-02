<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: every existing lot type gets an initial rate record, effective from its creation date.
        $lotTypes = DB::table('lot_types')->get();

        foreach ($lotTypes as $lotType) {
            DB::table('lot_type_rates')->insert([
                'residence_id' => $lotType->residence_id,
                'lot_type_id' => $lotType->id,
                'amount' => $lotType->monthly_amount,
                'effective_date' => $lotType->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('lot_types', function (Blueprint $table) {
            $table->dropColumn('monthly_amount');
        });
    }

    public function down(): void
    {
        Schema::table('lot_types', function (Blueprint $table) {
            $table->unsignedInteger('monthly_amount')->default(0)->after('name');
        });

        // Restore each lot type's monthly_amount from its latest rate.
        $lotTypes = DB::table('lot_types')->get();

        foreach ($lotTypes as $lotType) {
            $latestRate = DB::table('lot_type_rates')
                ->where('lot_type_id', $lotType->id)
                ->orderByDesc('effective_date')
                ->first();

            if ($latestRate) {
                DB::table('lot_types')->where('id', $lotType->id)->update(['monthly_amount' => $latestRate->amount]);
            }
        }
    }
};
