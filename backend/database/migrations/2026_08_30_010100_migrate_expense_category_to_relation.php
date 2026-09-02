<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('residence_id')->constrained()->cascadeOnDelete();
        });

        // Map each expense's free-text category to a matching (or newly created) category row.
        $expenses = DB::table('expenses')->get();

        foreach ($expenses as $expense) {
            $category = DB::table('expense_categories')
                ->where('residence_id', $expense->residence_id)
                ->where('name', $expense->category)
                ->first();

            $categoryId = $category?->id ?? DB::table('expense_categories')->insertGetId([
                'residence_id' => $expense->residence_id,
                'name' => $expense->category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('expenses')->where('id', $expense->id)->update(['expense_category_id' => $categoryId]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable(false)->change();
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable()->after('residence_id');
        });

        DB::table('expenses')->orderBy('id')->each(function ($expense) {
            $category = DB::table('expense_categories')->where('id', $expense->expense_category_id)->first();
            DB::table('expenses')->where('id', $expense->id)->update(['category' => $category?->name ?? 'Autre']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->dropConstrainedForeignId('expense_category_id');
        });
    }
};
