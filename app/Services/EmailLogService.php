<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\InteractionEventType;
use App\Models\InteractionTemplate;
use App\Models\User;
use Illuminate\Support\Str;

class EmailLogService
{
    public function __construct(private MailgunService $mailgun)
    {
    }

    public function sendFromTemplate(
        string $to,
        InteractionTemplate $template,
        array $payload,
        ?User $user = null,
        ?string $contactId = null,
        ?InteractionEventType $eventType = null,
        ?string $locale = null,
        array $fallbacks = []
    ): EmailLog {
        $rendered = app(InteractionTemplateService::class)->render($template, $payload, $locale, $fallbacks);

        $log = EmailLog::create([
            'user_id' => $user?->id,
            'contact_id' => $contactId,
            'email_address' => $to,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'locale' => $rendered['locale'] ?? $locale,
            'event_type_id' => $eventType?->id,
            'subject' => $rendered['subject'],
            'status' => 'queued',
            'metadata' => $payload ?: null,
        ]);

        $result = $this->mailgun->send($to, $rendered['subject'] ?? 'Notification', $rendered['body']);
        if (!$result['ok']) {
            $log->status = 'failed';
            $log->error_message = (string) ($result['error'] ?? 'unknown');
            $log->save();
            return $log;
        }

        $log->status = 'sent';
        $log->sent_at = now();
        $log->provider_message_id = $result['id'] ?? Str::uuid();
        $log->save();

        return $log;
    }
}
