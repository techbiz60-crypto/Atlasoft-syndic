<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_assemblies', function (Blueprint $table) {
            // Everything the convocation letter needs beyond the date
            // (Loi 18-00, art. 16 mukarrar 4: lieu, heure et ordre du jour
            // précis, envoyés au moins 15 jours avant la réunion).
            $table->string('location')->nullable()->after('held_on');
            $table->time('meeting_time')->nullable()->after('location');
            $table->json('agenda')->nullable()->after('meeting_time');
            // Filled in by the admin once they've actually sent it — the
            // app has no way to know when a letter physically went out.
            $table->date('convocation_sent_at')->nullable()->after('agenda');
        });
    }

    public function down(): void
    {
        Schema::table('general_assemblies', function (Blueprint $table) {
            $table->dropColumn(['location', 'meeting_time', 'agenda', 'convocation_sent_at']);
        });
    }
};
