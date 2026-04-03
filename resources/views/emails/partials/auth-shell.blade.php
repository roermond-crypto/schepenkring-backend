<!DOCTYPE html>
<html lang="{{ $emailLocale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine ?? ($appName ?? config('app.name', 'Schepenkring')) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    @if(!empty($preheader))
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#edf3f7;margin:0;padding:0;width:100%;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <tr>
                        <td style="padding-bottom:18px;text-align:center;">
                            @if(!empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="{{ $appName ?? config('app.name', 'Schepenkring') }}" style="display:block;margin:0 auto 10px auto;max-width:200px;width:100%;height:auto;border:0;">
                            @endif
                            <div style="font-size:14px;letter-spacing:0.22em;text-transform:uppercase;color:#4f6b7d;font-weight:700;">
                                {{ $appName ?? config('app.name', 'Schepenkring') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #d8e2ea;border-radius:24px;padding:36px 32px;box-shadow:0 18px 38px rgba(15,23,42,0.08);">
                            @if(!empty($badge))
                                <div style="display:inline-block;margin-bottom:18px;padding:8px 14px;border-radius:999px;background-color:#e0edf5;color:#0b4a6f;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">
                                    {{ $badge }}
                                </div>
                            @endif

                            <h1 style="margin:0 0 14px 0;font-size:32px;line-height:1.18;color:#0f172a;font-weight:700;">
                                {{ $headline }}
                            </h1>

                            @if(!empty($greeting))
                                <p style="margin:0 0 16px 0;font-size:16px;line-height:1.7;color:#334155;">
                                    {{ $greeting }}
                                </p>
                            @endif

                            @foreach(($introLines ?? []) as $line)
                                <p style="margin:0 0 16px 0;font-size:16px;line-height:1.7;color:#334155;">
                                    {{ $line }}
                                </p>
                            @endforeach

                            @if(!empty($code))
                                @if(!empty($codeLabel))
                                    <p style="margin:24px 0 10px 0;font-size:13px;line-height:1.5;color:#64748b;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;">
                                        {{ $codeLabel }}
                                    </p>
                                @endif
                                <div style="margin:0 0 24px 0;padding:18px 20px;border-radius:18px;background-color:#f5f9fc;border:1px solid #d8e2ea;font-size:34px;line-height:1.2;letter-spacing:0.24em;text-align:center;font-weight:700;color:#0b4a6f;">
                                    {{ $code }}
                                </div>
                            @endif

                            @foreach(($secondaryLines ?? []) as $line)
                                <p style="margin:0 0 16px 0;font-size:16px;line-height:1.7;color:#334155;">
                                    {{ $line }}
                                </p>
                            @endforeach

                            @if(!empty($primaryActionUrl) && !empty($primaryActionLabel))
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 18px 0;">
                                    <tr>
                                        <td align="center" style="border-radius:999px;background-color:#0b4a6f;">
                                            <a href="{{ $primaryActionUrl }}" style="display:inline-block;padding:15px 28px;border-radius:999px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;">
                                                {{ $primaryActionLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if(!empty($primaryActionSupportText))
                                <p style="margin:0 0 18px 0;font-size:14px;line-height:1.7;color:#64748b;">
                                    {{ $primaryActionSupportText }}
                                </p>
                            @endif

                            @if(!empty($fallbackUrl))
                                <div style="margin:0 0 18px 0;padding:14px 16px;border-radius:16px;background-color:#f8fafc;border:1px solid #e2e8f0;">
                                    @if(!empty($fallbackLabel))
                                        <p style="margin:0 0 8px 0;font-size:13px;line-height:1.5;color:#64748b;font-weight:700;">
                                            {{ $fallbackLabel }}
                                        </p>
                                    @endif
                                    <p style="margin:0;word-break:break-all;font-size:13px;line-height:1.6;color:#0b4a6f;">
                                        <a href="{{ $fallbackUrl }}" style="color:#0b4a6f;text-decoration:underline;">{{ $fallbackUrl }}</a>
                                    </p>
                                </div>
                            @endif

                            @if(!empty($outro))
                                <p style="margin:0 0 18px 0;font-size:15px;line-height:1.7;color:#475569;">
                                    {{ $outro }}
                                </p>
                            @endif

                            @if(!empty($footer))
                                <p style="margin:24px 0 0 0;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;line-height:1.7;color:#64748b;">
                                    {{ $footer }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
