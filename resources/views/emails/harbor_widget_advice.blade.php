<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Harbor Widget Performance Update</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:24px 28px;background:#0b1f3a;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;">NautiSecure</div>
                            <div style="font-size:13px;opacity:0.8;">Harbor widget performance</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#0f172a;">Weekly performance summary</h1>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Hi {{ $harbor->name }}, here is your harbor widget performance summary for the week starting {{ $metric->week_start->toDateString() }}.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;margin:0 0 18px 0;">
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">CTR</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $metric->ctr }}% <span style="font-weight:400;color:#64748b;">(benchmark {{ $benchmark }}%)</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Visibility rate</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $metric->visible_rate }}%</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Clicks</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $metric->clicks }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;">Reliability score</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;">{{ $metric->reliability_score }}</td>
                                </tr>
                            </table>

                            @if(!empty($advice->user_message))
                                <p style="margin:0 0 10px 0;font-size:14px;line-height:1.6;color:#0f172a;"><strong>Summary:</strong> {{ $advice->user_message }}</p>
                            @endif

                            @if(!empty($advice->issues))
                                <p style="margin:16px 0 8px 0;font-size:14px;font-weight:600;color:#0f172a;">Key issues</p>
                                <ul style="margin:0 0 10px 18px;color:#475569;font-size:14px;line-height:1.6;">
                                    @foreach($advice->issues as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if(!empty($advice->suggestions))
                                <p style="margin:16px 0 8px 0;font-size:14px;font-weight:600;color:#0f172a;">Recommended improvements</p>
                                <ul style="margin:0 0 10px 18px;color:#475569;font-size:14px;line-height:1.6;">
                                    @foreach($advice->suggestions as $suggestion)
                                        <li>{{ $suggestion }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            Need help? Reply to this email or contact support via the dashboard.
                        </td>
                    </tr>
                </table>
                <div style="margin-top:14px;font-size:11px;color:#94a3b8;">© {{ date('Y') }} NautiSecure</div>
            </td>
        </tr>
    </table>
</body>
</html>
