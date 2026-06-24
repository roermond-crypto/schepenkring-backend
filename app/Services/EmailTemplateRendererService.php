<?php

namespace App\Services;

class EmailTemplateRendererService
{
    public const TAGS = [
        'location_name', 'location_phone', 'location_email', 'location_address',
        'location_logo', 'location_url',
        'boat_name', 'boat_brand', 'boat_model', 'boat_price', 'boat_url', 'boat_image',
        'buyer_name', 'buyer_email', 'buyer_phone',
        'seller_name', 'seller_email',
        'offer_amount', 'counter_offer_amount',
        'booking_date', 'booking_time',
        'chat_link', 'offer_link', 'contract_link', 'brochure_link',
        'password_reset_link', 'verification_link',
    ];

    public const TAG_DESCRIPTIONS = [
        'location_name'        => 'Naam van de vestiging',
        'location_phone'       => 'Telefoonnummer van de vestiging',
        'location_email'       => 'E-mailadres van de vestiging',
        'location_address'     => 'Adres van de vestiging',
        'location_logo'        => 'Logo URL van de vestiging',
        'location_url'         => 'Website URL van de vestiging',
        'boat_name'            => 'Naam van het schip',
        'boat_brand'           => 'Merk van het schip',
        'boat_model'           => 'Model van het schip',
        'boat_price'           => 'Vraagprijs van het schip',
        'boat_url'             => 'URL naar de boot advertentie',
        'boat_image'           => 'Afbeelding URL van het schip',
        'buyer_name'           => 'Naam van de koper',
        'buyer_email'          => 'E-mailadres van de koper',
        'buyer_phone'          => 'Telefoonnummer van de koper',
        'seller_name'          => 'Naam van de verkoper',
        'seller_email'         => 'E-mailadres van de verkoper',
        'offer_amount'         => 'Bedrag van het bod',
        'counter_offer_amount' => 'Bedrag van het tegenbod',
        'booking_date'         => 'Datum van de bezichtiging/boeking',
        'booking_time'         => 'Tijdstip van de bezichtiging/boeking',
        'chat_link'            => 'Link naar het chatgesprek',
        'offer_link'           => 'Link naar het bod',
        'contract_link'        => 'Link naar het contract',
        'brochure_link'        => 'Link naar de brochure',
        'password_reset_link'  => 'Link voor wachtwoord reset',
        'verification_link'    => 'Link voor e-mailverificatie',
    ];

    /**
     * Required tags per template type
     */
    public const REQUIRED_TAGS_PER_TYPE = [
        'offer_received_buyer'        => ['buyer_name', 'boat_name', 'offer_amount', 'location_name'],
        'offer_received_seller'       => ['seller_name', 'boat_name', 'offer_amount', 'location_name', 'offer_link'],
        'seller_counter_offer'        => ['buyer_name', 'boat_name', 'counter_offer_amount', 'offer_link'],
        'seller_accept_offer'         => ['buyer_name', 'boat_name', 'offer_amount', 'location_name', 'contract_link'],
        'seller_reject_offer'         => ['buyer_name', 'boat_name', 'location_name'],
        'viewing_request_buyer'       => ['buyer_name', 'boat_name', 'booking_date', 'booking_time', 'location_name'],
        'viewing_request_location'    => ['buyer_name', 'buyer_email', 'boat_name', 'booking_date', 'booking_time'],
        'callback_request'            => ['buyer_name', 'buyer_phone', 'location_name'],
        'brochure_request'            => ['buyer_name', 'boat_name', 'brochure_link'],
        'question_received'           => ['buyer_name', 'boat_name', 'location_name'],
        'chat_message_email'          => ['buyer_name', 'chat_link', 'location_name'],
        'seller_invite'               => ['seller_name', 'location_name', 'verification_link'],
        'contract_signing'            => ['buyer_name', 'boat_name', 'contract_link'],
        'booking_confirmation'        => ['buyer_name', 'boat_name', 'booking_date', 'booking_time'],
        'password_invite'             => ['buyer_name', 'password_reset_link'],
    ];

    /**
     * Render blocks array into a full HTML email string.
     *
     * @param  array       $blocks          Array of block objects
     * @param  string      $lang            Language code: nl|en|de|fr
     * @param  array       $tags            Tag replacements ['location_name' => 'Schepenkring', ...]
     * @param  array|null  $locationBranding  ['primary_color' => '#C8102E', ...]
     * @return string
     */
    public function render(array $blocks, string $lang, array $tags = [], ?array $locationBranding = null): string
    {
        $primaryColor = $locationBranding['primary_color'] ?? '#C8102E';
        $bodyBg       = '#f4f4f4';

        $blocksHtml = '';
        foreach ($blocks as $block) {
            $blocksHtml .= $this->renderBlock($block, $lang, $tags, $primaryColor);
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Email</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
</head>
<body style="margin:0;padding:0;background-color:{$bodyBg};font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{$bodyBg};">
  <tr>
    <td align="center" style="padding:20px 10px;">
      <!--[if mso]>
      <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="600"><tr><td>
      <![endif]-->
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <tr>
          <td style="padding:30px 40px;">
            {$blocksHtml}
          </td>
        </tr>
      </table>
      <!--[if mso]>
      </td></tr></table>
      <![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Replace {{tag}} placeholders in a string.
     */
    public function replaceTags(string $content, array $tags): string
    {
        foreach ($tags as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $content);
        }
        return $content;
    }

    /**
     * Get list of tags used in the given blocks.
     */
    public function extractUsedTags(array $blocks): array
    {
        $json = json_encode($blocks);
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $json, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Get list of required tags for a template type.
     */
    public function requiredTagsForType(string $type): array
    {
        return self::REQUIRED_TAGS_PER_TYPE[$type] ?? [];
    }

    /**
     * Check which required tags are missing from the provided tag keys.
     */
    public function missingRequiredTags(string $type, array $providedTagKeys): array
    {
        $required = $this->requiredTagsForType($type);
        return array_values(array_diff($required, $providedTagKeys));
    }

    // ─── Private block renderers ──────────────────────────────────────────────

    private function renderBlock(array $block, string $lang, array $tags, string $primaryColor): string
    {
        $type     = $block['type'] ?? '';
        $settings = $block['settings'] ?? [];

        $html = match ($type) {
            'logo'          => $this->renderLogo($settings, $lang, $tags, $primaryColor),
            'header'        => $this->renderHeader($settings, $lang, $tags, $primaryColor),
            'text'          => $this->renderText($settings, $lang, $tags),
            'rich_text'     => $this->renderRichText($settings, $lang, $tags),
            'button'        => $this->renderButton($settings, $lang, $tags, $primaryColor),
            'image'         => $this->renderImage($settings, $lang, $tags),
            'divider'       => $this->renderDivider(),
            'spacer'        => $this->renderSpacer($settings),
            'footer'        => $this->renderFooter($settings, $lang, $tags),
            'signature'     => $this->renderSignature($settings, $lang, $tags),
            'social_links'  => $this->renderSocialLinks($settings, $lang, $tags),
            'boat_card'     => $this->renderBoatCard($settings, $lang, $tags, $primaryColor),
            'offer_card'    => $this->renderOfferCard($settings, $lang, $tags, $primaryColor),
            'seller_card'   => $this->renderSellerCard($settings, $lang, $tags),
            'buyer_card'    => $this->renderBuyerCard($settings, $lang, $tags),
            'location_card' => $this->renderLocationCard($settings, $lang, $tags),
            'contract_card' => $this->renderContractCard($settings, $lang, $tags, $primaryColor),
            default         => '',
        };

        return $html;
    }

    private function langVal(array $settings, string $key, string $lang, string $fallback = ''): string
    {
        $data = $settings[$key] ?? [];
        if (is_array($data)) {
            return (string) ($data[$lang] ?? $data['nl'] ?? $fallback);
        }
        return (string) ($data ?? $fallback);
    }

    private function renderLogo(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $src = $this->replaceTags($s['src'] ?? '{{location_logo}}', $tags);
        $alt = $this->replaceTags($s['alt'] ?? '{{location_name}}', $tags);
        $height = (int) ($s['height'] ?? 50);

        return <<<HTML
<div style="text-align:center;margin-bottom:20px;">
  <img src="{$src}" alt="{$alt}" style="height:{$height}px;width:auto;max-width:200px;" />
</div>
HTML;
    }

    private function renderHeader(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $content = $this->langVal($s, 'content', $lang);
        $content = $this->replaceTags($content, $tags);
        $align   = $s['align'] ?? 'left';

        return <<<HTML
<h1 style="font-size:24px;font-weight:700;color:#1a1a1a;margin:0 0 16px 0;line-height:1.3;text-align:{$align};">{$content}</h1>
HTML;
    }

    private function renderText(array $s, string $lang, array $tags): string
    {
        $content = $this->langVal($s, 'content', $lang);
        $content = $this->replaceTags($content, $tags);
        $content = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        return <<<HTML
<p style="font-size:15px;line-height:1.6;color:#333333;margin:0 0 16px 0;">{$content}</p>
HTML;
    }

    private function renderRichText(array $s, string $lang, array $tags): string
    {
        $html = $this->langVal($s, 'html', $lang);
        $html = $this->replaceTags($html, $tags);

        return <<<HTML
<div style="font-size:15px;line-height:1.6;color:#333333;margin:0 0 16px 0;">{$html}</div>
HTML;
    }

    private function renderButton(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $label = $this->langVal($s, 'label', $lang, 'Klik hier');
        $label = $this->replaceTags($label, $tags);
        $url   = $this->replaceTags($s['url'] ?? '#', $tags);
        $color = $s['color'] ?? $primaryColor;
        $align = $s['align'] ?? 'center';

        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">
  <tr>
    <td align="{$align}">
      <!--[if mso]>
      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{$url}" style="height:44px;v-text-anchor:middle;width:200px;" arcsize="10%" stroke="f" fillcolor="{$color}">
        <w:anchorlock/>
        <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">{$label}</center>
      </v:roundrect>
      <![endif]-->
      <!--[if !mso]><!-->
      <a href="{$url}" target="_blank" style="background-color:{$color};border-radius:6px;color:#ffffff;display:inline-block;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;line-height:44px;text-align:center;text-decoration:none;padding:0 24px;-webkit-text-size-adjust:none;">{$label}</a>
      <!--<![endif]-->
    </td>
  </tr>
</table>
HTML;
    }

    private function renderImage(array $s, string $lang, array $tags): string
    {
        $src   = $this->replaceTags($s['src'] ?? '', $tags);
        $alt   = $this->replaceTags($s['alt'] ?? '', $tags);
        $align = $s['align'] ?? 'center';

        if (empty($src)) {
            return '';
        }

        return <<<HTML
<div style="text-align:{$align};margin:16px 0;">
  <img src="{$src}" alt="{$alt}" style="max-width:100%;height:auto;border-radius:4px;" />
</div>
HTML;
    }

    private function renderDivider(): string
    {
        return '<hr style="border:none;border-top:1px solid #eeeeee;margin:20px 0;" />';
    }

    private function renderSpacer(array $s): string
    {
        $height = (int) ($s['height'] ?? 20);
        return "<div style=\"height:{$height}px;\"></div>";
    }

    private function renderFooter(array $s, string $lang, array $tags): string
    {
        $content = $this->langVal($s, 'content', $lang);
        $content = $this->replaceTags($content, $tags);
        $content = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        return <<<HTML
<p style="font-size:12px;color:#999999;text-align:center;margin:24px 0 0 0;line-height:1.5;">{$content}</p>
HTML;
    }

    private function renderSignature(array $s, string $lang, array $tags): string
    {
        $name  = $this->replaceTags($s['name'] ?? '', $tags);
        $title = $this->replaceTags($s['title'] ?? '', $tags);
        $phone = $this->replaceTags($s['phone'] ?? '', $tags);
        $email = $this->replaceTags($s['email'] ?? '', $tags);
        $avatar = $this->replaceTags($s['avatar'] ?? '', $tags);

        $avatarHtml = $avatar
            ? "<img src=\"{$avatar}\" alt=\"\" style=\"width:48px;height:48px;border-radius:50%;margin-right:12px;vertical-align:middle;\" />"
            : '';

        $phoneHtml = $phone ? "<span style=\"color:#666;font-size:13px;\">&#128222; {$phone}</span><br>" : '';
        $emailHtml = $email ? "<a href=\"mailto:{$email}\" style=\"color:#666;font-size:13px;text-decoration:none;\">{$email}</a><br>" : '';

        return <<<HTML
<div style="margin:20px 0;padding:16px;background-color:#f9f9f9;border-radius:6px;border-left:3px solid #eeeeee;">
  {$avatarHtml}
  <div style="display:inline-block;vertical-align:middle;">
    <strong style="color:#1a1a1a;font-size:14px;">{$name}</strong><br>
    <span style="color:#888;font-size:13px;">{$title}</span><br>
    {$phoneHtml}
    {$emailHtml}
  </div>
</div>
HTML;
    }

    private function renderSocialLinks(array $s, string $lang, array $tags): string
    {
        $links = $s['links'] ?? [];

        if (empty($links)) {
            return '';
        }

        $linksHtml = '';
        foreach ($links as $link) {
            $platform = htmlspecialchars($link['platform'] ?? '', ENT_QUOTES);
            $url      = $this->replaceTags($link['url'] ?? '#', $tags);
            $linksHtml .= "<a href=\"{$url}\" target=\"_blank\" style=\"color:#555555;text-decoration:none;margin:0 8px;font-size:13px;\">{$platform}</a>";
        }

        return <<<HTML
<div style="text-align:center;margin:16px 0;">
  {$linksHtml}
</div>
HTML;
    }

    private function renderBoatCard(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $boatName  = $this->replaceTags($s['boat_name'] ?? '{{boat_name}}', $tags);
        $boatBrand = $this->replaceTags($s['boat_brand'] ?? '{{boat_brand}}', $tags);
        $boatModel = $this->replaceTags($s['boat_model'] ?? '{{boat_model}}', $tags);
        $boatPrice = $this->replaceTags($s['boat_price'] ?? '{{boat_price}}', $tags);
        $boatUrl   = $this->replaceTags($s['boat_url'] ?? '{{boat_url}}', $tags);
        $boatImage = $this->replaceTags($s['boat_image'] ?? '{{boat_image}}', $tags);
        $btnLabel  = $this->langVal($s, 'button_label', $lang, 'Bekijk schip');

        $imageHtml = $boatImage
            ? "<img src=\"{$boatImage}\" alt=\"{$boatName}\" style=\"width:100%;max-height:200px;object-fit:cover;border-radius:6px 6px 0 0;display:block;\" />"
            : '';

        return <<<HTML
<div style="border:1px solid #eeeeee;border-radius:8px;overflow:hidden;margin:16px 0;">
  {$imageHtml}
  <div style="padding:16px;">
    <p style="margin:0 0 4px 0;font-size:18px;font-weight:700;color:#1a1a1a;">{$boatName}</p>
    <p style="margin:0 0 4px 0;font-size:14px;color:#666666;">{$boatBrand} {$boatModel}</p>
    <p style="margin:0 0 12px 0;font-size:16px;font-weight:600;color:{$primaryColor};">{$boatPrice}</p>
    <a href="{$boatUrl}" target="_blank" style="background-color:{$primaryColor};color:#ffffff;text-decoration:none;padding:8px 16px;border-radius:4px;font-size:14px;font-weight:bold;display:inline-block;">{$btnLabel}</a>
  </div>
</div>
HTML;
    }

    private function renderOfferCard(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $offerAmount = $this->replaceTags($s['offer_amount'] ?? '{{offer_amount}}', $tags);
        $boatName    = $this->replaceTags($s['boat_name'] ?? '{{boat_name}}', $tags);
        $date        = $this->replaceTags($s['date'] ?? '{{booking_date}}', $tags);
        $labelAmount = $this->langVal($s, 'label_amount', $lang, 'Bod');
        $labelBoat   = $this->langVal($s, 'label_boat', $lang, 'Schip');
        $labelDate   = $this->langVal($s, 'label_date', $lang, 'Datum');

        return <<<HTML
<div style="border:1px solid #eeeeee;border-radius:8px;padding:16px;margin:16px 0;background-color:#fafafa;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:6px 0;font-size:13px;color:#888;width:40%;">{$labelBoat}</td>
      <td style="padding:6px 0;font-size:14px;color:#1a1a1a;font-weight:600;">{$boatName}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;font-size:13px;color:#888;">{$labelAmount}</td>
      <td style="padding:6px 0;font-size:20px;color:{$primaryColor};font-weight:700;">{$offerAmount}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;font-size:13px;color:#888;">{$labelDate}</td>
      <td style="padding:6px 0;font-size:14px;color:#1a1a1a;">{$date}</td>
    </tr>
  </table>
</div>
HTML;
    }

    private function renderSellerCard(array $s, string $lang, array $tags): string
    {
        $name  = $this->replaceTags($s['seller_name'] ?? '{{seller_name}}', $tags);
        $email = $this->replaceTags($s['seller_email'] ?? '{{seller_email}}', $tags);
        $phone = $this->replaceTags($s['seller_phone'] ?? '', $tags);

        $phoneHtml = $phone ? "<tr><td style=\"padding:4px 0;font-size:13px;color:#888;\">Telefoon</td><td style=\"padding:4px 0;font-size:14px;color:#1a1a1a;\">{$phone}</td></tr>" : '';

        return <<<HTML
<div style="border:1px solid #eeeeee;border-radius:8px;padding:16px;margin:16px 0;background-color:#fafafa;">
  <p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;">Verkoper</p>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;width:35%;">Naam</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$name}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;">E-mail</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$email}</td>
    </tr>
    {$phoneHtml}
  </table>
</div>
HTML;
    }

    private function renderBuyerCard(array $s, string $lang, array $tags): string
    {
        $name  = $this->replaceTags($s['buyer_name'] ?? '{{buyer_name}}', $tags);
        $email = $this->replaceTags($s['buyer_email'] ?? '{{buyer_email}}', $tags);
        $phone = $this->replaceTags($s['buyer_phone'] ?? '{{buyer_phone}}', $tags);

        return <<<HTML
<div style="border:1px solid #eeeeee;border-radius:8px;padding:16px;margin:16px 0;background-color:#fafafa;">
  <p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;">Koper</p>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;width:35%;">Naam</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$name}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;">E-mail</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$email}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;">Telefoon</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$phone}</td>
    </tr>
  </table>
</div>
HTML;
    }

    private function renderLocationCard(array $s, string $lang, array $tags): string
    {
        $name    = $this->replaceTags($s['location_name'] ?? '{{location_name}}', $tags);
        $address = $this->replaceTags($s['location_address'] ?? '{{location_address}}', $tags);
        $phone   = $this->replaceTags($s['location_phone'] ?? '{{location_phone}}', $tags);
        $email   = $this->replaceTags($s['location_email'] ?? '{{location_email}}', $tags);

        return <<<HTML
<div style="border:1px solid #eeeeee;border-radius:8px;padding:16px;margin:16px 0;background-color:#fafafa;">
  <p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;">{$name}</p>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;width:35%;">Adres</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$address}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;">Telefoon</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$phone}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:13px;color:#888;">E-mail</td>
      <td style="padding:4px 0;font-size:14px;color:#1a1a1a;">{$email}</td>
    </tr>
  </table>
</div>
HTML;
    }

    private function renderContractCard(array $s, string $lang, array $tags, string $primaryColor): string
    {
        $contractLink = $this->replaceTags($s['contract_link'] ?? '{{contract_link}}', $tags);
        $btnLabel     = $this->langVal($s, 'button_label', $lang, 'Contract ondertekenen');

        return <<<HTML
<div style="border:2px solid {$primaryColor};border-radius:8px;padding:20px;margin:16px 0;text-align:center;">
  <p style="margin:0 0 12px 0;font-size:15px;color:#1a1a1a;">Uw contract is klaar om te ondertekenen.</p>
  <a href="{$contractLink}" target="_blank" style="background-color:{$primaryColor};color:#ffffff;text-decoration:none;padding:10px 24px;border-radius:4px;font-size:15px;font-weight:bold;display:inline-block;">{$btnLabel}</a>
</div>
HTML;
    }
}
