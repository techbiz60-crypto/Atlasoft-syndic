<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Convocation AG {{ $year }}</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; font-size: 12px; }
        .header { width: 100%; border-bottom: 3px solid #059685; padding-bottom: 14px; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .residence-name { font-size: 18px; font-weight: bold; color: #0d4e48; }
        .residence-address { font-size: 11px; color: #64748b; margin-top: 2px; }
        .doc-title { font-size: 14px; font-weight: bold; text-align: right; color: #1e293b; }
        .doc-date { font-size: 11px; text-align: right; color: #64748b; margin-top: 3px; }
        h1 { font-size: 16px; text-align: center; color: #0d4e48; margin: 0 0 22px; }
        .intro { margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.details td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        table.details td.label { color: #64748b; width: 30%; }
        table.details td.value { font-weight: bold; color: #1e293b; }
        .agenda-title { font-size: 13px; font-weight: bold; color: #1e293b; margin: 22px 0 10px; }
        ol.agenda { margin: 0 0 22px; padding-inline-start: 20px; }
        ol.agenda li { margin-bottom: 8px; }
        .legal-notice { background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 14px 18px; margin-bottom: 22px; font-size: 11px; color: #7c2d12; }
        .legal-notice strong { display: block; margin-bottom: 4px; }
        .signature { margin-top: 40px; font-size: 12px; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%;">
                <div class="residence-name">@arabic($residence->name)</div>
                @if($residence->address)
                    <div class="residence-address">@arabic($residence->address)</div>
                @endif
            </td>
            <td style="width: 40%;">
                <div class="doc-title">CONVOCATION</div>
                <div class="doc-date">Assemblée générale — exercice {{ $year }}</div>
                <div class="doc-date">Émise le {{ now()->translatedFormat('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <h1>Convocation à l'assemblée générale ordinaire</h1>

    <p class="intro">
        Mesdames, Messieurs les copropriétaires, vous êtes convoqués à l'assemblée générale ordinaire du
        syndicat des copropriétaires, appelée à statuer sur les comptes de l'exercice {{ $year }} et les
        questions inscrites à l'ordre du jour ci-dessous.
    </p>

    <table class="details">
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $assembly->held_on?->translatedFormat('l d F Y') ?? 'À définir' }}</td>
        </tr>
        <tr>
            <td class="label">Heure</td>
            <td class="value">{{ $assembly->meeting_time ? substr($assembly->meeting_time, 0, 5) : 'À définir' }}</td>
        </tr>
        <tr>
            <td class="label">Lieu</td>
            <td class="value">@arabic($assembly->location ?? 'À définir')</td>
        </tr>
    </table>

    <div class="agenda-title">Ordre du jour</div>
    @if(!empty($assembly->agenda))
        <ol class="agenda">
            @foreach($assembly->agenda as $point)
                <li>@arabic($point)</li>
            @endforeach
        </ol>
    @else
        <p style="color: #94a3b8;">Aucun point à l'ordre du jour n'a encore été renseigné.</p>
    @endif

    <div class="legal-notice">
        <strong>Rappel important</strong>
        Conformément à la loi 18-00 relative au statut de la copropriété, tout copropriétaire n'ayant pas
        réglé les charges de copropriété exigibles ne pourra être admis à participer à cette assemblée
        générale. Nous vous invitons à régulariser votre situation avant la date de la réunion.
    </div>

    <p>
        Si vous ne pouvez pas assister personnellement à cette réunion, vous pouvez vous faire représenter
        par un mandataire de votre choix, muni d'une procuration écrite (dans la limite légale de trois
        pouvoirs par mandataire).
    </p>

    <div class="signature">
        Le syndic,<br>
        @arabic($residence->name)
    </div>

    <div class="footer">
        Cette convocation doit être adressée aux copropriétaires au moins 15 jours avant la date de la
        réunion (loi 18-00, art. 16 mukarrar 4).
    </div>
</body>
</html>
