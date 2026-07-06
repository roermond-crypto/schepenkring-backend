<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boot aanmelding ontvangen</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Wij hebben uw aanmelding voor {{ $boatName }} ontvangen. Score: {{ $scoreTotal }}%.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;margin:0;padding:0;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <!-- Logo -->
                    <tr>
                        <td style="padding-bottom:18px;text-align:center;">
                            <div style="font-size:14px;letter-spacing:0.22em;text-transform:uppercase;color:#003566;font-weight:900;">
                                Schepenkring
                            </div>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #d8e2ea;border-radius:24px;padding:36px 32px;box-shadow:0 18px 38px rgba(15,23,42,0.08);">

                            <!-- Badge -->
                            <div style="display:inline-block;margin-bottom:18px;padding:8px 16px;border-radius:999px;background-color:#003566;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;">
                                Aanmelding ontvangen
                            </div>

                            <h1 style="margin:0 0 8px 0;font-size:26px;line-height:1.2;color:#0f172a;font-weight:700;">
                                Bedankt, {{ $sellerName }}!
                            </h1>
                            <p style="margin:0 0 24px 0;font-size:15px;line-height:1.7;color:#475569;">
                                Wij hebben uw aanmelding voor <strong>{{ $boatName }}</strong> ontvangen bij <strong>{{ $locationName }}</strong>.
                                Onze makelaar neemt zo snel mogelijk contact met u op.
                            </p>

                            <!-- Score card -->
                            <div style="margin-bottom:24px;border-radius:16px;background-color:#f8fafc;border:1px solid #e2e8f0;padding:20px 24px;">
                                <p style="margin:0 0 12px 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#64748b;">
                                    Volledigheid aanmelding
                                </p>

                                <!-- Score bar -->
                                <div style="margin-bottom:16px;">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                        <span style="font-size:13px;color:#475569;">Totaalscore</span>
                                        <span style="font-size:18px;font-weight:700;color:{{ $scoreTotal >= 70 ? '#16a34a' : ($scoreTotal >= 50 ? '#d97706' : '#dc2626') }};">{{ $scoreTotal }}%</span>
                                    </div>
                                    <div style="height:8px;border-radius:4px;background-color:#e2e8f0;overflow:hidden;">
                                        <div style="height:8px;border-radius:4px;width:{{ $scoreTotal }}%;background-color:{{ $scoreTotal >= 70 ? '#16a34a' : ($scoreTotal >= 50 ? '#d97706' : '#dc2626') }};"></div>
                                    </div>
                                </div>

                                <!-- Breakdown rows -->
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    @foreach([
                                        ['label' => "Foto's", 'value' => $breakdown['photos'] ?? 0, 'detail' => "{$photoCount}/{$photoTarget}"],
                                        ['label' => 'Beschrijving', 'value' => $breakdown['description'] ?? 0, 'detail' => "{$descriptionLength}/{$descriptionTarget} tekens"],
                                        ['label' => 'Basisgegevens', 'value' => $breakdown['fields'] ?? 0, 'detail' => ''],
                                        ['label' => 'Documenten', 'value' => $breakdown['documents'] ?? 0, 'detail' => ''],
                                    ] as $row)
                                    <tr>
                                        <td style="padding:4px 0;font-size:13px;color:#475569;">{{ $row['label'] }}{{ $row['detail'] ? ' ('.$row['detail'].')' : '' }}</td>
                                        <td style="padding:4px 0;text-align:right;font-size:13px;font-weight:600;color:{{ $row['value'] >= 70 ? '#16a34a' : ($row['value'] >= 40 ? '#d97706' : '#dc2626') }};">{{ $row['value'] }}%</td>
                                    </tr>
                                    @endforeach
                                </table>
                            </div>

                            @if($hasMissingItems)
                            <!-- Missing items -->
                            <div style="margin-bottom:24px;border-radius:12px;border:1px solid #fca5a5;background-color:#fef2f2;padding:16px 20px;">
                                <p style="margin:0 0 10px 0;font-size:13px;font-weight:700;color:#b91c1c;text-transform:uppercase;letter-spacing:0.06em;">
                                    Nog aan te vullen
                                </p>
                                @foreach($missingItems as $item)
                                <p style="margin:0 0 6px 0;font-size:13px;color:#7f1d1d;">
                                    • {{ $item['label'] }}
                                </p>
                                @endforeach
                            </div>

                            <!-- Resume CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:24px;width:100%;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $resumeUrl }}" style="display:inline-block;padding:15px 32px;border-radius:999px;background-color:#C8102E;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">
                                            Aanmelding aanvullen →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px 0;font-size:13px;color:#64748b;text-align:center;">
                                Of kopieer deze link: <a href="{{ $resumeUrl }}" style="color:#003566;word-break:break-all;">{{ $resumeUrl }}</a>
                            </p>
                            @else
                            <p style="margin:0 0 24px 0;font-size:14px;color:#16a34a;font-weight:600;">
                                ✓ Uw aanmelding is volledig! Onze makelaar bekijkt uw aanvraag.
                            </p>
                            @endif

                            <!-- Next steps -->
                            <div style="margin-bottom:24px;border-radius:16px;background-color:#f0f4f8;border:1px solid #d1dde8;padding:20px 24px;">
                                <p style="margin:0 0 14px 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#003566;">
                                    Volgende stappen
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    @foreach([
                                        ['num' => '1', 'title' => 'Controleer uw e-mail', 'body' => 'Gebruik de link in dit bericht om extra foto\'s of documenten toe te voegen.'],
                                        ['num' => '2', 'title' => 'Maak een account aan', 'body' => 'Uw profiel wordt automatisch ingevuld met de gegevens die u heeft ingevoerd.'],
                                        ['num' => '3', 'title' => 'Onze makelaar neemt contact op', 'body' => 'Wij beoordelen uw aanmelding en nemen binnen één werkdag contact op.'],
                                    ] as $step)
                                    <tr>
                                        <td style="padding:8px 0;vertical-align:top;width:36px;">
                                            <div style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background-color:#003566;color:#ffffff;font-size:12px;font-weight:700;text-align:center;line-height:26px;">
                                                {{ $step['num'] }}
                                            </div>
                                        </td>
                                        <td style="padding:8px 0 8px 10px;vertical-align:top;">
                                            <p style="margin:0;font-size:13px;font-weight:700;color:#0f172a;">{{ $step['title'] }}</p>
                                            <p style="margin:2px 0 0 0;font-size:12px;color:#64748b;">{{ $step['body'] }}</p>
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                            </div>

                            <p style="margin:0;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;line-height:1.7;">
                                Heeft u vragen? Neem dan contact op met {{ $locationName }}.<br>
                                Dit e-mail is verstuurd naar {{ $intake->seller_email }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
