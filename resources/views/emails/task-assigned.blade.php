<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>New Task Assigned</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:24px 28px;background:#0b1f3a;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;">NautiSecure</div>
                            <div style="font-size:13px;opacity:0.8;">Task notification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#0f172a;">New task assigned</h1>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Hello {{ $task->assignedTo?->name ?? 'there' }}, you have been assigned a new task by {{ $creator->name }}.
                            </p>

                            <div style="border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;background:#f8fafc;margin:0 0 18px 0;">
                                <div style="font-size:14px;color:#64748b;margin-bottom:6px;">Task</div>
                                <div style="font-size:16px;font-weight:600;color:#0f172a;">{{ $task->title }}</div>
                                @if(!empty($task->description))
                                    <div style="margin-top:8px;font-size:14px;line-height:1.6;color:#475569;">{{ $task->description }}</div>
                                @endif
                                @if(!empty($task->due_date))
                                    <div style="margin-top:10px;font-size:13px;color:#64748b;">Due date: {{ $task->due_date->format('Y-m-d') }}</div>
                                @endif
                            </div>

                            <div style="text-align:center;">
                                <a href="{{ config('app.frontend_url') }}" style="display:inline-block;background:#0ea5e9;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 20px;border-radius:10px;">
                                    Open Dashboard
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            Please log in to view and manage this task.
                        </td>
                    </tr>
                </table>
                <div style="margin-top:14px;font-size:11px;color:#94a3b8;">© {{ date('Y') }} NautiSecure</div>
            </td>
        </tr>
    </table>
</body>
</html>
