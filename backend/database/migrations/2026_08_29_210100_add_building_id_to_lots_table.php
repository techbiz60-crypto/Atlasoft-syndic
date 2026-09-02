<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->after('residence_id')->constrained()->cascadeOnDelete();
        });

        // Backfill: give every residence that already has lots a default building.
        $residenceIds = DB::table('lots')->distinct()->pluck('residence_id');

        foreach ($residenceIds as $residenceId) {
            $buildingId = DB::table('buildings')->insertGetId([
                'residence_id' => $residenceId,
                'name' => 'Bâtiment principal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lots')
                ->where('residence_id', $residenceId)
                ->update(['building_id' => $buildingId]);
        }

        Schema::table('lots', function (Blueprint $table) {
            // A plain index on residence_id is added first because the composite unique
            // index below is also the only index currently supporting the residence_id
            // foreign key — MySQL refuses to drop it otherwise.
            $table->index('residence_id');
            $table->dropUnique(['residence_id', 'number']);
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedBigInteger('building_id')->nullable(false)->change();
            $table->unique(['building_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropUnique(['building_id', 'number']);
            $table->dropConstrainedForeignId('building_id');
            $table->dropIndex(['residence_id']);
            $table->unique(['residence_id', 'number']);
        });
    }
};
