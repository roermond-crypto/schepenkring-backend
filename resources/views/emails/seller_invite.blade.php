<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Uitnodiging Schepenkring</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f6f9; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e6ef; }
        .header { background: #0B1F3A; padding: 28px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; }
        .body { padding: 32px; color: #333; font-size: 15px; line-height: 1.7; }
        .boat-box { background: #f0f5ff; border: 1px solid #c7d8f5; border-radius: 6px; padding: 14px 18px; margin: 20px 0; }
        .boat-box p { margin: 0; font-weight: 600; color: #0B1F3A; font-size: 16px; }
        .btn { display: inline-block; background: #1E3A8A; color: #ffffff; text-decoration: none; padding: 13px 28px; border-radius: 6px; font-weight: 700; font-size: 15px; margin: 20px 0 8px; }
        .secondary-link { color: #1E3A8A; font-size: 13px; }
        .footer { background: #f4f6f9; padding: 18px 32px; color: #888; font-size: 12px; border-top: 1px solid #e0e6ef; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Schepenkring</h1>
    </div>
    <div class="body">
        <p>Beste {{ $sellerName }},</p>
        <p>
            <strong>{{ $locationName }}</strong> heeft u uitgenodigd om uw boot te beheren via het Schepenkring platform.
            U ontvangt via ons platform biedingen, bezichtigingsverzoeken en berichten van geïnteresseerde kopers.
        </p>

        <div class="boat-box">
            <p>🚢 {{ $boatName }}</p>
        </div>

        <p>Log in op uw account om uw advertentie te bekijken en te reageren op berichten:</p>

        <a href="{{ $loginUrl }}" class="btn">Inloggen op Schepenkring</a>

        <p>
            <a href="{{ $resetUrl }}" class="secondary-link">Wachtwoord vergeten? Stel hier een nieuw wachtwoord in.</a>
        </p>

        <p>Met vriendelijke groet,<br /><strong>{{ $locationName }}</strong> via Schepenkring</p>
    </div>
    <div class="footer">
        Dit bericht is verstuurd via Schepenkring. Neem contact op met {{ $locationName }} als u vragen heeft.
    </div>
</div>
</body>
</html>
