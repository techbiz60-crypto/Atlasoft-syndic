<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundCall;
use App\Models\Lot;
use App\Models\LotType;
use App\Models\Residence;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use App\PaymentMethod;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A second, self-contained residence for exercising multi-tenancy: a
 * realistic mix of good payers, partial payers, never-billed months and
 * pre-platform debt, deliberately built through the normal model layer
 * (Lot::create(), Residence::create()…) rather than raw inserts, so every
 * booted() side effect — the first LotOwner row, the Trésorier's default
 * permissions — fires exactly as it would for a residence created through
 * the app itself.
 *
 * Entirely independent of the Sahel Oued residences: different admin,
 * different building, different lot type, nothing shared.
 */
class AlAmalResidenceSeeder extends Seeder
{
    private const MONTHLY_RATE = 250;

    private const DUPLEX_RATE = 400;

    public function run(): void
    {
        $residence = Residence::create([
            'name' => 'Résidence AL AMAL',
            'address' => 'Avenue Al Massira, Marrakech',
            'lots_count' => 20,
            'bank_rib' => '007 780 0009876543210987 65',
            'opening_balance' => 0,
        ]);

        $admin = User::create([
            'residence_id' => $residence->id,
            'role' => Role::Admin,
            'name' => 'Youssef Bennani',
            'email' => 'admin@al-amal-syndic.ma',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $tresorier = User::create([
            'residence_id' => $residence->id,
            'role' => Role::Tresorier,
            'name' => 'Fatima Zahra Idrissi',
            'email' => 'tresoriere@al-amal-syndic.ma',
            'password' => Hash::make('password'),
        ]);
        $tresorier->markEmailAsVerified();

        $conseil = User::create([
            'residence_id' => $residence->id,
            'role' => Role::Conseil,
            'name' => 'Karim El Fassi',
            'email' => 'conseil@al-amal-syndic.ma',
            'password' => Hash::make('password'),
        ]);
        $conseil->markEmailAsVerified();

        $building = Building::create([
            'residence_id' => $residence->id,
            'name' => 'Bloc A',
        ]);

        $appartement = LotType::create(['residence_id' => $residence->id, 'name' => 'Appartement']);
        $appartement->rates()->create([
            'residence_id' => $residence->id,
            'amount' => self::MONTHLY_RATE,
            'effective_date' => '2020-01-01',
        ]);

        $duplex = LotType::create(['residence_id' => $residence->id, 'name' => 'Duplex']);
        $duplex->rates()->create([
            'residence_id' => $residence->id,
            'amount' => self::DUPLEX_RATE,
            'effective_date' => '2020-01-01',
        ]);

        $owners = [
            ['Hicham Ouazzani', '+212661000001'], ['Naima Bouzid', '+212661000002'],
            ['Rachid Amrani', '+212661000003'], ['Salma Tazi', '+212661000004'],
            ['Omar Chraibi', '+212661000005'], ['Khadija Berrada', '+212661000006'],
            ['Younes Sqalli', '+212661000007'], ['Amina Lahlou', '+212661000008'],
            ['Mehdi Guessous', '+212661000009'], ['Zineb Alaoui', '+212661000010'],
            ['Adil Benjelloun', '+212661000011'], ['Souad Cherkaoui', '+212661000012'],
            ['Tarik Fassi Fihri', '+212661000013'], ['Imane Kettani', '+212661000014'],
            ['Bilal Skalli', '+212661000015'], ['Meryem Bennis', '+212661000016'],
            ['Anas Belhaj', '+212661000017'], ['Houda Sebti', '+212661000018'],
            ['Yassine Rifai', '+212661000019'], ['Sara Benabdellah + Karim Benabdellah', '+212661000020'],
        ];

        $lots = [];

        foreach ($owners as $index => [$ownerName, $phone]) {
            $number = $index + 1;
            $isDuplex = $number % 5 === 0; // 4 duplexes out of 20, the rest are standard apartments.

            $lots[] = Lot::create([
                'residence_id' => $residence->id,
                'building_id' => $building->id,
                'lot_type_id' => $isDuplex ? $duplex->id : $appartement->id,
                'number' => (string) $number,
                'floor' => (string) intdiv($index, 2),
                'owner_name' => $ownerName,
                'owner_phone' => $phone,
                'owner_email' => null,
            ]);
        }

        // Pre-platform debt on two lots — separate from the year-by-year
        // cotisation history below, exactly like a real onboarding.
        $this->givePreplatformDebt($residence, $lots[4], 1200, '2025-06-01');
        $this->givePreplatformDebt($residence, $lots[11], 600, '2025-01-01');

        // January through August 2026: every lot except the two "never
        // billed" ones below gets a fund call per month, at its lot type's
        // rate. Payment behaviour then varies per lot so Impayés/Trésorerie/
        // AG have something real to show.
        $months = range(1, 8);

        foreach ($lots as $index => $lot) {
            $rate = $lot->lot_type_id === $duplex->id ? self::DUPLEX_RATE : self::MONTHLY_RATE;

            // Lots 15 and 16 (index 14, 15): the last two months were never
            // even billed — the exact "lazy fund call" gap Impayés has to
            // project rather than just reading existing rows.
            $skipMonths = in_array($index, [14, 15], true) ? [7, 8] : [];

            foreach ($months as $month) {
                if (in_array($month, $skipMonths, true)) {
                    continue;
                }

                $fundCall = FundCall::create([
                    'residence_id' => $residence->id,
                    'lot_id' => $lot->id,
                    'amount' => $rate,
                    'period' => Carbon::create(2026, $month, 1),
                ]);

                $this->maybePay($residence, $fundCall, $index, $month, $rate);
            }
        }

        // One resident pays four months in a single bank transfer — the
        // grouped-payment case (Paiements/Grand livre must show it as one
        // line, not four).
        $this->createBulkPayment($residence, $lots[7], [5, 6, 7, 8], self::MONTHLY_RATE);

        // One resident pays ahead of time, into a month not due yet — the
        // "current month isn't overdue" boundary.
        $this->createBulkPayment($residence, $lots[19], [9, 10], self::DUPLEX_RATE, Carbon::create(2026, 9, 5));

        // Grant this resident a login, exercising the resident-access path
        // (and the "must be verified on creation" fix) for a second tenant.
        $resident = User::create([
            'residence_id' => $residence->id,
            'lot_id' => $lots[0]->id,
            'role' => Role::Coproprietaire,
            'name' => $lots[0]->owner_name,
            'email' => 'resident1@al-amal-syndic.ma',
            'password' => Hash::make('password'),
        ]);
        $resident->markEmailAsVerified();

        $categories = [
            'Eau' => [420, '2026-03-10'],
            'Électricité' => [680, '2026-04-12'],
            'Gardiennage' => [1500, '2026-05-01'],
            'Entretien' => [950, '2026-07-18'],
        ];

        foreach ($categories as $name => [$amount, $paidAt]) {
            $category = ExpenseCategory::create(['residence_id' => $residence->id, 'name' => $name]);
            Expense::create([
                'residence_id' => $residence->id,
                'expense_category_id' => $category->id,
                'method' => PaymentMethod::Virement,
                'paid_at' => $paidAt,
                'label' => $name.' — Bloc A',
                'amount' => $amount,
            ]);
        }

        $revenueCategory = RevenueCategory::create(['residence_id' => $residence->id, 'name' => 'Location salle commune']);
        Revenue::create([
            'residence_id' => $residence->id,
            'revenue_category_id' => $revenueCategory->id,
            'method' => PaymentMethod::Especes,
            'received_at' => '2026-06-20',
            'label' => 'Location pour événement',
            'amount' => 300,
        ]);

        $this->command?->info("Résidence AL AMAL créée (id {$residence->id}) — admin: {$admin->email} / password");
    }

    private function givePreplatformDebt(Residence $residence, Lot $lot, int $amount, string $since): void
    {
        FundCall::create([
            'residence_id' => $residence->id,
            'lot_id' => $lot->id,
            'amount' => $amount,
            'period' => $since,
            'is_opening_balance' => true,
        ]);
    }

    /**
     * Payment behaviour by lot index: mostly good payers, a handful of
     * partial payers who stop mid-year, and two who never pay at all —
     * enough variety for Impayés and the AG recap to have something to
     * differentiate.
     */
    private function maybePay(Residence $residence, FundCall $fundCall, int $lotIndex, int $month, int $rate): void
    {
        $isNeverPaid = in_array($lotIndex, [16, 17], true);
        $isPartialPayer = in_array($lotIndex, [2, 6, 10, 13], true);
        // Lot 8 (index 7) is settled entirely through the grouped payment
        // created below — paying it here too would double it up.
        $isBulkPaidElsewhere = $lotIndex === 7;

        if ($isNeverPaid || $isBulkPaidElsewhere) {
            return;
        }

        if ($isPartialPayer && $month > 5) {
            return;
        }

        $fundCall->payments()->create([
            'residence_id' => $residence->id,
            'amount' => $rate,
            'paid_at' => Carbon::create(2026, $month, 1)->addDays(4),
            'method' => PaymentMethod::Virement,
        ]);
    }

    private function createBulkPayment(Residence $residence, Lot $lot, array $months, int $rate, ?Carbon $paidAt = null): void
    {
        $batchId = (string) Str::uuid();
        $paidAt ??= Carbon::create(2026, max($months), 1)->addDays(3);

        foreach ($months as $month) {
            $fundCall = FundCall::firstOrCreate(
                ['lot_id' => $lot->id, 'period' => Carbon::create(2026, $month, 1)->toDateString(), 'is_opening_balance' => false],
                ['residence_id' => $residence->id, 'amount' => $rate],
            );

            $fundCall->payments()->create([
                'residence_id' => $residence->id,
                'batch_id' => $batchId,
                'amount' => $rate,
                'paid_at' => $paidAt,
                'method' => PaymentMethod::Cheque,
            ]);
        }
    }
}
