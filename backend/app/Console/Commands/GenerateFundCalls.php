<?php

namespace App\Console\Commands;

use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotTypeRate;
use App\Models\Residence;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateFundCalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fund-calls:generate {--residence= : Only generate for this residence ID} {--period= : Period month, format Y-m-d, defaults to the first day of the current month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère les appels de fonds mensuels manquants pour chaque appartement, au montant du barème en vigueur pour cette période.';

    public function handle(): int
    {
        $period = $this->option('period')
            ? Carbon::parse($this->option('period'))->startOfMonth()
            : Carbon::now()->startOfMonth();

        $residences = $this->option('residence')
            ? Residence::where('id', $this->option('residence'))->get()
            : Residence::all();

        $created = 0;
        $skipped = 0;

        foreach ($residences as $residence) {
            $lots = Lot::withoutGlobalScopes()
                ->where('residence_id', $residence->id)
                ->get();

            foreach ($lots as $lot) {
                $exists = FundCall::withoutGlobalScopes()
                    ->where('lot_id', $lot->id)
                    ->whereDate('period', $period)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $rate = LotTypeRate::withoutGlobalScopes()
                    ->where('lot_type_id', $lot->lot_type_id)
                    ->whereDate('effective_date', '<=', $period)
                    ->orderByDesc('effective_date')
                    ->first();

                if (! $rate) {
                    $skipped++;

                    continue;
                }

                FundCall::withoutGlobalScopes()->create([
                    'residence_id' => $residence->id,
                    'lot_id' => $lot->id,
                    'amount' => $rate->amount,
                    'period' => $period,
                ]);

                $created++;
            }
        }

        $this->info("{$created} appel(s) de fonds créé(s) pour la période {$period->format('Y-m')}.");

        if ($skipped > 0) {
            $this->warn("{$skipped} lot(s) ignoré(s) : aucun barème en vigueur pour cette période.");
        }

        return self::SUCCESS;
    }
}
