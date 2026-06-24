<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw bod op {{ $boatName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $offer->buyer_name ?? 'Een koper' }} heeft {{ $formattedAmount }} geboden op {{ $boatName }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;margin:0;padding:0;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <!-- Logo -->
                    <tr>
                        <td style="padding-bottom:18px;text-align:center;">
                            <div style="font-size:14px;letter-spacing:0.22em;text-transform:uppercase;color:#4f6b7d;font-weight:700;">
                                {{ config('app.name', 'Schepenkring') }}
                            </div>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #d8e2ea;border-radius:24px;padding:36px 32px;box-shadow:0 18px 38px rgba(15,23,42,0.08);">

                            <!-- Badge -->
                            <div style="display:inline-block;margin-bottom:18px;padding:8px 14px;border-radius:999px;background-color:#fef3c7;color:#92400e;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">
                                Nieuw bod
                            </div>

                            <h1 style="margin:0 0 8px 0;font-size:28px;line-height:1.2;color:#0f172a;font-weight:700;">
                                Er is een bod ontvangen
                            </h1>
                            <p style="margin:0 0 24px 0;font-size:16px;line-height:1.7;color:#334155;">
                                Hallo {{ $seller->name }},<br>
                                Er is een bod uitgebracht op <strong>{{ $boatName }}</strong>.
                            </p>

                            @if($belowMinimum)
                            <div style="margin-bottom:20px;padding:14px 16px;border-radius:12px;background-color:#fef2f2;border:1px solid #fca5a5;">
                                <p style="margin:0;font-size:14px;color:#b91c1c;font-weight:600;">
                                    ⚠️ Dit bod ligt onder het minimumbod voor dit aanbod.
                                </p>
                            </div>
                            @endif

                            <!-- Offer details table -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
                                <tr style="background-color:#f8fafc;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;width:40%;">Bod</td>
                                    <td style="padding:12px 16px;font-size:20px;font-weight:700;color:#003566;">{{ $formattedAmount }}</td>
                                </tr>
                                <tr style="border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Vraagprijs</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">{{ $formattedAsking }}</td>
                                </tr>
                                <tr style="background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Koper</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">{{ $offer->buyer_name ?? '—' }}</td>
                                </tr>
                                @if($offer->buyer_email)
                                <tr style="border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">E-mail</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">
                                        <a href="mailto:{{ $offer->buyer_email }}" style="color:#003566;">{{ $offer->buyer_email }}</a>
                                    </td>
                                </tr>
                                @endif
                                @if($offer->buyer_phone)
                                <tr style="background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Telefoon</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">{{ $offer->buyer_phone }}</td>
                                </tr>
                                @endif
                                @if($buyerMessage)
                                <tr style="border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;vertical-align:top;">Bericht</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">{{ $buyerMessage }}</td>
                                </tr>
                                @endif
                            </table>

                            <p style="margin:0 0 20px 0;font-size:15px;font-weight:600;color:#0f172a;">Wat wilt u doen?</p>

                            <!-- CTA Buttons -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:0 8px 12px 0;" width="33%">
                                        <a href="{{ $acceptUrl }}" style="display:block;padding:14px 10px;border-radius:12px;background-color:#16a34a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;text-align:center;">
                                            ✓ Accepteren
                                        </a>
                                    </td>
                                    <td style="padding:0 8px 12px 8px;" width="33%">
                                        <a href="{{ $rejectUrl }}" style="display:block;padding:14px 10px;border-radius:12px;background-color:#dc2626;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;text-align:center;">
                                            ✕ Afwijzen
                                        </a>
                                    </td>
                                    @if($counterUrl)
                                    <td style="padding:0 0 12px 8px;" width="33%">
                                        <a href="{{ $counterUrl }}" style="display:block;padding:14px 10px;border-radius:12px;background-color:#003566;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;text-align:center;">
                                            ↔ Tegenbod
                                        </a>
                                    </td>
                                    @endif
                                </tr>
                            </table>

                            <p style="margin:0;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;line-height:1.7;color:#64748b;">
                                De links zijn 7 dagen geldig na ontvangst van dit e-mail. U ontvangt dit bericht omdat u als makelaar bent gekoppeld aan dit aanbod in Schepenkring.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
