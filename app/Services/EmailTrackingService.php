<?php

namespace App\Services;

use App\Models\CampaignTarget;
use App\Models\Conversation;
use App\Models\EmailEvent;
use App\Models\Message;
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
        $event = EmailEvent::where('token', $token)->first();
        if (! $event) {
            return;
        }

        $isFirstOpen = $event->recordOpen();

        // Only the first open posts to Chat Hub (spec §10) — repeat pixel
        // loads from email-client image re-fetching would otherwise spam
        // the thread with "opened" messages on every scroll.
        if ($isFirstOpen) {
            $this->postToChatHub($event, 'campaign_email_opened', 'E-mail geopend door ontvanger.');
        }
    }

    public function recordClick(string $token, string $url): void
    {
        $event = EmailEvent::where('token', $token)->first();
        if (! $event) {
            return;
        }

        $isNewUrl = $event->recordClick($url);

        if ($isNewUrl) {
            $this->postToChatHub($event, 'campaign_email_clicked', "Link aangeklikt in e-mail: {$url}");
        }
    }

    private function postToChatHub(EmailEvent $event, string $messageType, string $text): void
    {
        if (! $event->conversation_id) {
            return;
        }

        $conversation = Conversation::find($event->conversation_id);
        if (! $conversation) {
            return;
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'text' => $text,
            'body' => $text,
            'channel' => 'email',
            'message_type' => $messageType,
            'metadata' => ['email_event_id' => $event->id, 'source' => 'retell_voice'],
        ]);

        $conversation->last_message_at = now();
        $conversation->save();
    }
}
