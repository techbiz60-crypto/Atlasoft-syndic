<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu de paiement groupé</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 12px; }
        .header { width: 100%; border-bottom: 3px solid #059685; padding-bottom: 14px; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .residence-name { font-size: 18px; font-weight: bold; color: #0d4e48; }
        .residence-address { font-size: 11px; color: #64748b; margin-top: 2px; }
        .receipt-title { font-size: 14px; font-weight: bold; text-align: right; color: #1e293b; }
        .receipt-number { font-size: 11px; text-align: right; color: #64748b; margin-top: 3px; }
        .amount-box { background-color: #effefa; border: 1px solid #92fae2; border-radius: 6px; padding: 16px 20px; margin-bottom: 22px; text-align: center; }
        .amount-value { font-size: 26px; font-weight: bold; color: #08786c; }
        .amount-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.details td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        table.details td.label { color: #64748b; width: 45%; }
        table.details td.value { font-weight: bold; color: #1e293b; text-align: right; }
        .months-title { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 8px; }
        table.months { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.months th { text-align: left; font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid #e2e8f0; padding: 6px 0; }
        table.months td { padding: 7px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        table.months td.amount { text-align: right; font-weight: 600; }
        .rib-box { font-size: 11px; color: #64748b; margin-top: 10px; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%;">
                <div class="residence-name">{{ $residence->name }}</div>
                @if($residence->address)
                    <div class="residence-address">{{ $residence->address }}</div>
                @endif
            </td>
            <td style="width: 40%;">
                <div class="receipt-title">REÇU DE PAIEMENT GROUPÉ</div>
                <div class="receipt-number">N° {{ str_pad($payments->first()->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="receipt-number">Émis le {{ now()->translatedFormat('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="amount-value">{{ number_format($totalAmount, 0, ',', ' ') }} DH</div>
        <div class="amount-label">Montant total réglé — {{ $payments->count() }} mois</div>
    </div>

    <table class="details">
        <tr>
            <td class="label">Copropriétaire</td>
            <td class="value">{{ $payments->first()->owner_name ?? $lot->owner_name }}</td>
        </tr>
        <tr>
            <td class="label">Appartement</td>
            <td class="value">{{ $lot->number }} — {{ $lot->building->name }}</td>
        </tr>
        <tr>
            <td class="label">Date du paiement</td>
            <td class="value">{{ $paidAt->translatedFormat('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Méthode de paiement</td>
            <td class="value">{{ $methodLabel }}</td>
        </tr>
        @if($notes)
            <tr>
                <td class="label">Note</td>
                <td class="value">{{ $notes }}</td>
            </tr>
        @endif
    </table>

    <div class="months-title">Mois couverts par ce paiement</div>
    <table class="months">
        <thead>
            <tr>
                <th>Mois</th>
                <th style="text-align: right;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $item)
                <tr>
                    <td>{{ $item->fundCall->period->translatedFormat('F Y') }}</td>
                    <td class="amount">{{ number_format($item->amount, 0, ',', ' ') }} DH</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($residence->bank_rib)
        <div class="rib-box">RIB de la résidence : {{ $residence->bank_rib }}</div>
    @endif

    <div class="footer">
        Reçu généré automatiquement par {{ config('app.name') }} — ne constitue pas une facture fiscale.
    </div>
</body>
</html>
