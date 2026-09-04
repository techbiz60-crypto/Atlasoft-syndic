<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
        });

        // Seed sort_order from the current alphabetical order so existing
        // lists don't visually jump around on first load after this migrates.
        $residenceIds = DB::table('expense_categories')->distinct()->pluck('residence_id');

        foreach ($residenceIds as $residenceId) {
            $categories = DB::table('expense_categories')
                ->where('residence_id', $residenceId)
                ->orderBy('name')
                ->get(['id']);

            foreach ($categories as $index => $category) {
                DB::table('expense_categories')->where('id', $category->id)->update(['sort_order' => $index]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
