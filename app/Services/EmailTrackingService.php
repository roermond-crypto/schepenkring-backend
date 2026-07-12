<?php

namespace App\Services;

use App\Models\CampaignTarget;
use App\Models\EmailEvent;
use Illuminate\Support\Str;

/**
 * Self-hosted email open/click tracking (pixel + click-redirect), not
 * provider-webhook-based — the production MAIL_MAILER isn't knowable from
 * here, so this works regardless of which one is actually configured.
 * Only wired into campaign email sends (CampaignService), not every mail
 * call site in the app.
 */
class EmailTrackingService
{
    public function createEvent(?CampaignTarget $target, string $templateKey, string $recipientEmail): EmailEvent
    {
        return EmailEvent::create([
            'token' => (string) Str::uuid(),
            'campaign_target_id' => $target?->id,
            'email_template_key' => $templateKey,
            'recipient_email' => $recipientEmail,
            'sent_at' => now(),
        ]);
    }

    /**
     * Injects a tracking pixel before </body> and rewrites every <a href="...">
     * to route through the click-redirect endpoint. Best-effort regex-based
     * rewriting — templates here are admin-authored blocks, not arbitrary
     * untrusted HTML, so this doesn't need a full HTML parser.
     */
    public function instrument(string $html, string $token): string
    {
        $pixelUrl = route('email.track', ['token' => $token]);
        $html = preg_replace_callback(
            '/<a\s+([^>]*?)href=(["\'])(.*?)\2([^>]*)>/i',
            function (array $matches) use ($token) {
                $url = $matches[3];
                if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
                    return $matches[0];
                }

                $tracked = route('email.click', ['token' => $token, 'url' => $url]);

                return '<a '.$matches[1].'href="'.$tracked.'"'.$matches[4].'>';
            },
            $html,
        ) ?? $html;

        $pixelTag = '<img src="'.$pixelUrl.'" width="1" height="1" alt="" style="display:none" />';

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $pixelTag.'</body>', $html);
        }

        return $html.$pixelTag;
    }

    public function recordOpen(string $token): void
    {
        EmailEvent::where('token', $token)->first()?->recordOpen();
    }

    public function recordClick(string $token, string $url): void
    {
        EmailEvent::where('token', $token)->first()?->recordClick($url);
    }
}
