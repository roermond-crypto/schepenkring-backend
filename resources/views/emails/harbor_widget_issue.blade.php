<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Harbor Widget Issue Detected</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:24px 28px;background:#7f1d1d;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;">NautiSecure</div>
                            <div style="font-size:13px;opacity:0.9;">Harbor widget alert</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#0f172a;">Issue detected</h1>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;">
                                A harbor widget issue was detected and may affect customer engagement.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;margin:0 0 18px 0;">
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Harbor</td>
                                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $harbor->name }} (ID {{ $harbor->id }})</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Checked at</td>
                                    <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ optional($snapshot->checked_at)->toDateTimeString() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Widget found</td>
                                    <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $snapshot->widget_found ? 'yes' : 'no' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Widget visible</td>
                                    <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-bottom:1px solid #e2e8f0;">{{ $snapshot->widget_visible ? 'yes' : 'no' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;color:#64748b;">Widget clickable</td>
                                    <td style="padding:12px 16px;font-size:14px;color:#0f172a;">{{ $snapshot->widget_clickable ? 'yes' : 'no' }}</td>
                                </tr>
                            </table>

                            @if(!empty($snapshot->console_errors))
                                <p style="margin:0 0 8px 0;font-size:14px;font-weight:600;color:#0f172a;">Console errors</p>
                                <ul style="margin:0 0 10px 18px;color:#475569;font-size:14px;line-height:1.6;">
                                    @foreach($snapshot->console_errors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <p style="margin:16px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Review the harbor widget setup and screenshot in the admin dashboard.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            This alert was generated automatically by the monitoring system.
                        </td>
                    </tr>
                </table>
                <div style="margin-top:14px;font-size:11px;color:#94a3b8;">© {{ date('Y') }} NautiSecure</div>
            </td>
        </tr>
    </table>
</body>
</html>
