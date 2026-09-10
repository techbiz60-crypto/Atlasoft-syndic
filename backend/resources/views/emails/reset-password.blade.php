<!DOCTYPE html>
<html lang="{{ $isArabic ? 'ar' : 'fr' }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atlasoft Syndic</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,0.08);">
                <tr>
                    <td align="center" style="padding:32px 32px 16px;">
                        <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="Atlasoft Syndic" width="120" style="display:block;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 0; text-align:{{ $isArabic ? 'right' : 'left' }};">
                        @if ($isArabic)
                            <h1 style="font-size:18px; color:#0f172a; margin:0 0 16px;">مرحبًا {{ $name }}،</h1>
                            <p style="font-size:14px; line-height:1.6; color:#475569; margin:0 0 24px;">
                                توصلنا بطلب لإعادة تعيين كلمة مرور حسابكم في Atlasoft Syndic. اضغطوا على الزر أدناه لاختيار كلمة مرور جديدة.
                            </p>
                        @else
                            <h1 style="font-size:18px; color:#0f172a; margin:0 0 16px;">Bonjour {{ $name }},</h1>
                            <p style="font-size:14px; line-height:1.6; color:#475569; margin:0 0 24px;">
                                Vous avez demandé la réinitialisation du mot de passe de votre compte Atlasoft Syndic. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:0 32px 24px;">
                        <a href="{{ $url }}" style="display:inline-block; background-color:#0891b2; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:8px;">
                            {{ $isArabic ? 'إعادة تعيين كلمة المرور' : 'Réinitialiser mon mot de passe' }}
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 32px; text-align:{{ $isArabic ? 'right' : 'left' }};">
                        <p style="font-size:12px; line-height:1.6; color:#94a3b8; margin:0;">
                            @if ($isArabic)
                                هذا الرابط صالح لمدة {{ $expireMinutes }} دقيقة. إذا لم تكونوا أنتم من طلب هذا، يمكنكم تجاهل هذه الرسالة — كلمة مروركم لن تتغير.
                            @else
                                Ce lien expire dans {{ $expireMinutes }} minutes. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email — votre mot de passe ne sera pas modifié.
                            @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
                        <p style="font-size:11px; color:#94a3b8; margin:0;">Atlasoft Syndic — {{ $isArabic ? 'تدبير النقابات السكنية' : 'Gestion de copropriétés' }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
