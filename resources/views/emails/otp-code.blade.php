<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Security Verification Code</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:24px 28px;background:#0b1f3a;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;">NautiSecure</div>
                            <div style="font-size:13px;opacity:0.8;">Security verification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#0f172a;">Your verification code</h1>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Use the code below to complete your {{ $purpose }}.
                            </p>

                            <div style="margin:18px 0 20px 0;padding:16px 20px;border:1px dashed #94a3b8;border-radius:12px;background:#f8fafc;text-align:center;">
                                <div style="font-size:28px;letter-spacing:6px;font-weight:700;color:#0f172a;">{{ $code }}</div>
                            </div>

                            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.6;color:#64748b;">
                                This code expires in {{ $ttlMinutes }} minutes and can only be used once.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            If you did not request this code, you can safely ignore this email.
                        </td>
                    </tr>
                </table>
                <div style="margin-top:14px;font-size:11px;color:#94a3b8;">© {{ date('Y') }} NautiSecure</div>
            </td>
        </tr>
    </table>
</body>
</html>
