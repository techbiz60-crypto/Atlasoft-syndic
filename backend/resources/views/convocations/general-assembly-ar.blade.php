<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>استدعاء الجمعية العامة {{ $year }}</title>
    <style>
        @page { margin: 40px 45px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; font-size: 12px; direction: rtl; text-align: right; }
        .header { width: 100%; border-bottom: 3px solid #059685; padding-bottom: 14px; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .residence-name { font-size: 18px; font-weight: bold; color: #0d4e48; }
        .residence-address { font-size: 11px; color: #64748b; margin-top: 2px; }
        .doc-title { font-size: 14px; font-weight: bold; text-align: left; color: #1e293b; }
        .doc-date { font-size: 11px; text-align: left; color: #64748b; margin-top: 3px; }
        h1 { font-size: 16px; text-align: center; color: #0d4e48; margin: 0 0 22px; }
        .intro { margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.details td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        table.details td.label { color: #64748b; width: 30%; }
        table.details td.value { font-weight: bold; color: #1e293b; }
        .agenda-title { font-size: 13px; font-weight: bold; color: #1e293b; margin: 22px 0 10px; }
        ol.agenda { margin: 0 30px 22px 0; padding: 0; }
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
            <td style="width: 40%; text-align: left;">
                <div class="doc-title">استدعاء</div>
                <div class="doc-date">الجمعية العامة — السنة المالية {{ $year }}</div>
                <div class="doc-date">صدر بتاريخ {{ now()->locale('ar')->translatedFormat('d/m/Y') }}</div>
            </td>
            <td style="width: 60%;">
                <div class="residence-name">@arabic($residence->name)</div>
                @if($residence->address)
                    <div class="residence-address">@arabic($residence->address)</div>
                @endif
            </td>
        </tr>
    </table>

    <h1>استدعاء لحضور الجمعية العامة العادية</h1>

    <p class="intro">
        السيدات والسادة الملاك المشتركون، تتم دعوتكم لحضور الجمعية العامة العادية لنقابة الملاك المشتركين،
        المنعقدة للبت في حسابات السنة المالية {{ $year }} والنقط المدرجة في جدول الأعمال أسفله.
    </p>

    <table class="details">
        <tr>
            <td class="label">التاريخ</td>
            <td class="value">{{ $assembly->held_on ? $assembly->held_on->locale('ar')->translatedFormat('l d F Y') : 'لم يُحدد بعد' }}</td>
        </tr>
        <tr>
            <td class="label">الساعة</td>
            <td class="value">{{ $assembly->meeting_time ? substr($assembly->meeting_time, 0, 5) : 'لم تُحدد بعد' }}</td>
        </tr>
        <tr>
            <td class="label">المكان</td>
            <td class="value">@arabic($assembly->location ?? 'لم يُحدد بعد')</td>
        </tr>
    </table>

    <div class="agenda-title">جدول الأعمال</div>
    @if(!empty($assembly->agenda))
        <ol class="agenda">
            @foreach($assembly->agenda as $point)
                <li>@arabic($point)</li>
            @endforeach
        </ol>
    @else
        <p style="color: #94a3b8;">لم يتم إدراج أي نقطة في جدول الأعمال بعد.</p>
    @endif

    <div class="legal-notice">
        <strong>تذكير مهم</strong>
        طبقًا للقانون رقم 18.00 المتعلق بنظام الملكية المشتركة للعقارات المبنية، كل مالك مشترك لم يؤد
        الواجبات المستحقة عليه لن يُقبل حضوره في هذه الجمعية العامة. ندعوكم إلى تسوية وضعيتكم قبل تاريخ
        الاجتماع.
    </div>

    <p>
        إذا تعذر عليكم حضور هذا الاجتماع شخصيًا، يمكنكم أن تُمثَّلوا بواسطة وكيل من اختياركم، بموجب توكيل
        كتابي (في حدود ثلاثة توكيلات كحد أقصى لكل وكيل).
    </p>

    <div class="signature">
        السنديك،<br>
        @arabic($residence->name)
    </div>

    <div class="footer">
        يجب توجيه هذا الاستدعاء إلى الملاك المشتركين قبل 15 يومًا على الأقل من تاريخ الاجتماع (القانون
        18.00، المادة 16 مكرر 4).
    </div>
</body>
</html>
