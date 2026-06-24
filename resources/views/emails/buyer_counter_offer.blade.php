<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tegenbod ontvangen voor {{ $boatName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        De makelaar heeft een tegenbod gedaan van {{ $formattedCounter }} op {{ $boatName }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;margin:0;padding:0;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <tr>
                        <td style="padding-bottom:18px;text-align:center;">
                            <div style="font-size:14px;letter-spacing:0.22em;text-transform:uppercase;color:#4f6b7d;font-weight:700;">
                                {{ config('app.name', 'Schepenkring') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #d8e2ea;border-radius:24px;padding:36px 32px;box-shadow:0 18px 38px rgba(15,23,42,0.08);">

                            <div style="display:inline-block;margin-bottom:18px;padding:8px 14px;border-radius:999px;background-color:#dbeafe;color:#1e3a8a;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">
                                Tegenbod
                            </div>

                            <h1 style="margin:0 0 8px 0;font-size:28px;line-height:1.2;color:#0f172a;font-weight:700;">
                                De makelaar heeft een tegenbod gedaan
                            </h1>
                            <p style="margin:0 0 24px 0;font-size:16px;line-height:1.7;color:#334155;">
                                Beste {{ $buyerName }},<br>
                                De makelaar van <strong>{{ $locationName }}</strong> heeft gereageerd op uw bod voor <strong>{{ $boatName }}</strong>.
                            </p>

                            <!-- Offer comparison -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
                                <tr style="background-color:#f8fafc;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;width:40%;">Uw bod</td>
                                    <td style="padding:12px 16px;font-size:16px;color:#334155;text-decoration:line-through;">{{ $formattedOriginal }}</td>
                                </tr>
                                <tr style="border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Tegenbod makelaar</td>
                                    <td style="padding:12px 16px;font-size:22px;font-weight:700;color:#003566;">{{ $formattedCounter }}</td>
                                </tr>
                                @if($counterMessage)
                                <tr style="background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;vertical-align:top;">Bericht</td>
                                    <td style="padding:12px 16px;font-size:15px;color:#334155;">{{ $counterMessage }}</td>
                                </tr>
                                @endif
                            </table>

                            <p style="margin:0 0 24px 0;font-size:15px;line-height:1.7;color:#475569;">
                                Wilt u reageren op dit tegenbod? Neem dan contact op via <a href="mailto:{{ $offer->location?->email ?? config('mail.from.address') }}" style="color:#003566;">{{ $offer->location?->email ?? config('mail.from.address') }}</a> of bel ons op {{ $offer->location?->phone ?? '' }}.
                            </p>

                            <p style="margin:0;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;line-height:1.7;color:#64748b;">
                                U ontvangt dit bericht omdat u een bod hebt uitgebracht op {{ $boatName }} via {{ $locationName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
