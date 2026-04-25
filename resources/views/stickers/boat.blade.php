<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boat Sticker - {{ $yacht->boat_name }}</title>
    <style>
        @page {
            margin: 0;
            size: 296.33mm 137.58mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 12px;
            background: #eef4fa;
            color: #11253f;
            font-family: Helvetica, Arial, sans-serif;
        }

        .sticker-shell {
            max-width: 1120px;
            margin: 0 auto;
        }

        .sticker-container {
            position: relative;
            width: 1120px;
            height: 520px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 18px;
            background:
                radial-gradient(circle at top left, rgba(34, 152, 198, 0.08), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f9fcff 100%);
            box-shadow: 0 10px 26px rgba(7, 32, 60, 0.10);
        }

        .header {
            position: absolute;
            top: 26px;
            left: 28px;
            width: 520px;
        }

        .brand {
            white-space: nowrap;
        }

        .brand-mark {
            display: inline-block;
            vertical-align: middle;
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(180deg, #12335a 0%, #174b79 62%, #2398c6 100%);
            color: #ffffff;
            text-align: center;
            line-height: 56px;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 0.02em;
            box-shadow: inset 0 -8px 12px rgba(255, 255, 255, 0.10);
        }

        .brand-copy {
            display: inline-block;
            vertical-align: middle;
            margin-left: 12px;
            line-height: 1;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #102743;
        }

        .brand-name .accent {
            color: #1d8fc1;
        }

        .status-pill {
            display: inline-block;
            margin-top: 14px;
            padding: 8px 14px;
            border: 1px solid #cfe0ec;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.86);
            color: #4b647f;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .main-content {
            position: absolute;
            top: 150px;
            left: 28px;
            width: 560px;
        }

        .title {
            margin: 0;
            color: #16304f;
            font-size: 74px;
            font-weight: 800;
            line-height: 0.95;
            letter-spacing: -0.03em;
            text-transform: uppercase;
        }

        .subtitle {
            margin: 6px 0 0;
            color: #138fbe;
            font-size: 68px;
            font-weight: 800;
            line-height: 0.95;
            letter-spacing: -0.03em;
            text-transform: uppercase;
        }

        .scan-msg {
            margin: 20px 0 0;
            color: #617286;
            font-size: 22px;
            line-height: 1.3;
        }

        .boat-name {
            margin-top: 14px;
            color: #36506f;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .qr-section {
            position: absolute;
            top: 24px;
            right: 28px;
            width: 332px;
            padding: 14px 14px 12px;
            background: #ffffff;
            border: 1px solid #dae5ef;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 10px 24px rgba(12, 41, 71, 0.08);
        }

        .qr-image {
            display: block;
            width: 300px;
            height: 300px;
            margin: 0 auto;
        }

        .qr-caption {
            margin-top: 12px;
            color: #607286;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .partner-strip {
            position: absolute;
            right: 19px;
            bottom: 87px;
            width: 344px;
            padding: 10px 14px;
            border-radius: 18px;
            text-align: center;
            z-index: 4;
        }

        .partner-logo {
            display: inline-block;
            vertical-align: middle;
            margin: 0 8px;
            color: #425d79;
        }

        .partner-logo.stack {
            text-align: left;
            line-height: 1.02;
        }

        .partner-logo .label {
            display: block;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.55);
        }

        .partner-logo.hiswa .label {
            font-size: 20px;
        }

        .partner-logo.nbms .label {
            font-size: 18px;
            color: #d48b2a;
        }

        .partner-logo.stack .label {
            font-size: 11px;
            max-width: 106px;
        }

        .wave-band {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 128px;
            z-index: 1;
        }

        .wave-band::before,
        .wave-band::after,
        .wave-band .wave-third {
            content: "";
            position: absolute;
            left: -5%;
            width: 110%;
            border-radius: 50% 50% 0 0;
        }

        .wave-band::before {
            bottom: -58px;
            height: 148px;
            background: linear-gradient(90deg, #112b52 0%, #184d7c 50%, #1796c5 100%);
        }

        .wave-band::after {
            bottom: 26px;
            height: 66px;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.94), rgba(197, 235, 250, 0.88));
            transform: rotate(-0.8deg);
        }

        .wave-band .wave-third {
            bottom: 10px;
            height: 44px;
            background: linear-gradient(90deg, rgba(189, 228, 245, 0.84), rgba(118, 201, 232, 0.92));
        }

        @media screen and (max-width: 980px) {
            body {
                padding: 8px;
            }

            .sticker-container {
                width: 100%;
                height: auto;
                min-height: 0;
                padding: 22px 18px 96px;
                border-radius: 16px;
            }

            .header,
            .main-content,
            .qr-section,
            .partner-strip {
                position: static;
                width: auto;
            }

            .header {
                margin-bottom: 18px;
            }

            .brand-mark {
                width: 48px;
                height: 48px;
                line-height: 48px;
                font-size: 28px;
                border-radius: 12px;
            }

            .brand-copy {
                margin-left: 10px;
            }

            .brand-name {
                font-size: 18px;
            }

            .status-pill {
                margin-top: 12px;
                font-size: 10px;
                letter-spacing: 0.12em;
            }

            .main-content {
                margin-bottom: 18px;
            }

            .title {
                font-size: 44px;
            }

            .subtitle {
                font-size: 42px;
            }

            .scan-msg {
                margin-top: 16px;
                font-size: 17px;
            }

            .boat-name {
                margin-top: 12px;
                font-size: 14px;
            }

            .qr-section {
                width: 100%;
                max-width: 320px;
                margin: 0 auto 20px;
            }

            .qr-image {
                width: 100%;
                max-width: 252px;
                height: auto;
            }

            .partner-strip {
                margin: 0 auto 18px;
                width: auto;
                max-width: 320px;
                padding: 10px 12px;
                text-align: center;
            }

            .partner-logo {
                margin: 0 6px 8px;
            }

            .partner-logo.hiswa .label {
                font-size: 17px;
            }

            .partner-logo.nbms .label {
                font-size: 16px;
            }

            .partner-logo.stack .label {
                font-size: 12px;
            }

            .wave-band {
                height: 92px;
            }

            .wave-band::before {
                bottom: -42px;
                height: 102px;
            }

            .wave-band::after {
                bottom: 10px;
                height: 42px;
            }

            .wave-band .wave-third {
                bottom: 3px;
                height: 30px;
            }
        }

        @media print {
            html,
            body {
                width: 296.33mm;
                height: 137.58mm;
            }

            body {
                padding: 0;
                background: #ffffff;
                overflow: hidden;
            }

            .sticker-shell {
                width: 296.33mm;
                max-width: none;
                margin: 0;
            }

            .sticker-container {
                width: 296.33mm;
                height: 137.58mm;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    @php
        $boatName = trim((string) ($yacht->boat_name ?? ''));
        $showBoatName = $boatName !== '' && !in_array(strtolower($boatName), ['-', 'n/a', 'na', 'null', 'undefined'], true);
    @endphp
    <div class="sticker-shell">
        <div class="sticker-container">
            <div class="header">
                <div class="brand">
                    <div class="brand-mark">N</div>
                    <div class="brand-copy">
                        <div class="brand-name">NAUTIC<span class="accent">SECURE</span></div>
                    </div>
                </div>
                <div class="status-pill">Boat Listing Sticker</div>
            </div>

            <div class="main-content">
                <h1 class="title">DEZE BOOT</h1>
                <h2 class="subtitle">IS TE KOOP</h2>
                <p class="scan-msg">Scan voor prijs, foto's en bezichtiging</p>
                @if ($showBoatName)
                    <div class="boat-name">{{ $boatName }}</div>
                @endif
            </div>

            <div class="qr-section">
                <img src="{{ $qrCodeSrc }}" class="qr-image" alt="QR Code">
                <div class="qr-caption">Scan met je telefoon</div>
            </div>

            <div class="partner-strip">
                <div class="partner-logo hiswa">
                    <span class="label">HISWA</span>
                </div>
                <div class="partner-logo nbms">
                    <span class="label">NBMS</span>
                </div>
                <div class="partner-logo stack">
                    <span class="label">NEDERLANDSE</span>
                    <span class="label">BOND VAN</span>
                    <span class="label">MAKELAARS</span>
                </div>
            </div>

            <div class="wave-band">
                <div class="wave-third"></div>
            </div>
        </div>
    </div>
</body>
</html>
