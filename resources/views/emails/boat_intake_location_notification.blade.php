<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuwe boot aanmelding</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;margin:0;padding:0;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <tr>
                        <td style="padding-bottom:18px;text-align:center;">
                            <div style="font-size:14px;letter-spacing:0.22em;text-transform:uppercase;color:#003566;font-weight:900;">
                                Schepenkring
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #d8e2ea;border-radius:24px;padding:36px 32px;">
                            <div style="display:inline-block;margin-bottom:18px;padding:8px 16px;border-radius:999px;background-color:#003566;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;">
                                Nieuwe boot aanmelding
                            </div>
                            <h1 style="margin:0 0 8px 0;font-size:24px;color:#0f172a;font-weight:700;">
                                {{ $boatLabel }} — {{ $sellerName }}
                            </h1>
                            <p style="margin:0 0 24px 0;font-size:15px;color:#475569;line-height:1.7;">
                                Er is een nieuwe boot aangemeld bij <strong>{{ $location->name }}</strong> via de website.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
                                @foreach([
                                    ['label'=>'Verkoper', 'value'=>$sellerName],
                                    ['label'=>'E-mail', 'value'=>$intake->seller_email ?? '—'],
                                    ['label'=>'Telefoon', 'value'=>$intake->seller_phone ?? '—'],
                                    ['label'=>'Boot', 'value'=>$boatLabel],
                                    ['label'=>'Vraagprijs', 'value'=>$intake->asking_price ? '€ '.number_format($intake->asking_price,0,',','.') : '—'],
                                    ['label'=> "Foto's", 'value'=>$intake->photo_count],
                                    ['label'=>'Score', 'value'=>($intake->intake_score ?? 0).'%'],
                                    ['label'=>'Status', 'value'=>$intake->status],
                                ] as $i => $row)
                                <tr style="{{ $i % 2 === 0 ? 'background-color:#f8fafc;' : '' }}{{ $i > 0 ? 'border-top:1px solid #e2e8f0;' : '' }}">
                                    <td style="padding:10px 16px;font-size:13px;font-weight:700;color:#64748b;width:38%;">{{ $row['label'] }}</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#0f172a;">{{ $row['value'] }}</td>
                                </tr>
                                @endforeach
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                                <tr>
                                    <td align="center" style="border-radius:999px;background-color:#003566;">
                                        <a href="{{ $adminUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                                            Bekijken in dashboard →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;">
                                Dit is een automatisch bericht van Schepenkring.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
