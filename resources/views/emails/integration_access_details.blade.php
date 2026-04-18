<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration access details</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; background: #f8fafc; padding: 24px;">
    <div style="max-width: 720px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;">
        <h2 style="margin: 0 0 8px;">Integration access details</h2>
        <p style="margin: 0 0 20px; color: #475569;">
            These access details were sent from the Schepenkring admin dashboard to an authorized recipient.
        </p>

        <table cellspacing="0" cellpadding="8" style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Integration</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $integration->integration_type }}</td>
            </tr>
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Label</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $integration->label ?: '—' }}</td>
            </tr>
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Environment</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $integration->environment }}</td>
            </tr>
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Status</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $integration->status }}</td>
            </tr>
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Username</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $integration->username ?: '—' }}</td>
            </tr>
            @if($passwordValue)
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0;"><strong>Password</strong></td>
                <td style="border-bottom: 1px solid #e2e8f0;">{{ $passwordValue }}</td>
            </tr>
            @endif
            @if($apiKeyValue)
            <tr>
                <td><strong>API key</strong></td>
                <td>{{ $apiKeyValue }}</td>
            </tr>
            @endif
        </table>
    </div>
</body>
</html>
