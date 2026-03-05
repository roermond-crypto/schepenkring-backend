<?php

namespace App\Services;

use App\Models\InteractionEventType;
use App\Models\InteractionTemplate;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class InteractionRouterService
{
    public function __construct(
        private ChatConversationService $chatService,
        private InteractionTemplateService $templateService,
        private EmailLogService $emailLogService,
        private InteractionHubService $hub,
        private LocaleService $locales
    ) {
    }

    public function dispatchEvent(string $eventKey, array $context): array
    {
        $eventType = InteractionEventType::where('key', $eventKey)->where('enabled', true)->first();
        if (!$eventType) {
            return ['status' => 'ignored', 'reason' => 'event_type_not_found'];
        }

        $templates = InteractionTemplate::where('event_type_id', $eventType->id)
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($templates as $template) {
            $results[] = $this->dispatchTemplate($template, $eventType, $context);
        }

        return [
            'status' => 'dispatched',
            'count' => count($results),
            'results' => $results,
        ];
    }

    private function dispatchTemplate(InteractionTemplate $template, InteractionEventType $eventType, array $context): array
    {
        $channel = $template->channel;
        $user = $this->resolveUser($context);
        $contactId = $context['contact_id'] ?? null;
        $locale = $this->resolveLocale($context, $user);
        $fallbacks = $this->locales->fallbackChain($locale);
        $conversation = $this->resolveConversation($context, $user, $contactId);
        if (!$conversation && ($user || $contactId)) {
            $conversation = $this->createConversation($context, $user);
        }
        $payload = $context['payload'] ?? $context;

        if ($channel === 'email') {
            $to = $context['email'] ?? $user?->email ?? null;
            if (!$to) {
                return ['channel' => 'email', 'status' => 'skipped', 'reason' => 'missing_email'];
            }

            $log = $this->emailLogService->sendFromTemplate($to, $template, $payload, $user, $contactId, $eventType, $locale, $fallbacks);
            $this->hub->recordEmail($log);

            return ['channel' => 'email', 'status' => $log->status, 'log_id' => $log->id];
        }

        if (!$conversation) {
            return ['channel' => $channel, 'status' => 'skipped', 'reason' => 'missing_conversation'];
        }

        $rendered = $this->templateService->render($template, $payload, $locale, $fallbacks);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'text' => $rendered['body'],
            'language' => $rendered['locale'] ?? $conversation->language_preferred,
            'channel' => $channel === 'chat' ? 'web' : $channel,
            'message_type' => 'system',
            'status' => null,
            'metadata' => [
                'template_id' => $template->id,
                'template_version' => $template->version,
                'template_locale' => $rendered['locale'] ?? null,
                'template_status' => $rendered['status'] ?? null,
                'event_type_id' => $eventType->id,
            ],
        ]);

        $this->hub->recordMessage($message, $conversation);

        return ['channel' => $channel, 'status' => 'queued', 'message_id' => $message->id];
    }

    private function resolveUser(array $context): ?User
    {
        if (!empty($context['user_id'])) {
            return User::find($context['user_id']);
        }

        if (!empty($context['user']) && $context['user'] instanceof User) {
            return $context['user'];
        }

        return null;
    }

    private function resolveConversation(array $context, ?User $user, ?string $contactId): ?Conversation
    {
        if (!empty($context['conversation_id'])) {
            return Conversation::find($context['conversation_id']);
        }

        if ($contactId) {
            return Conversation::where('contact_id', $contactId)->orderByDesc('last_message_at')->first();
        }

        if ($user) {
            return Conversation::where('user_id', $user->id)->orderByDesc('last_message_at')->first();
        }

        return null;
    }

    private function createConversation(array $context, ?User $user): ?Conversation
    {
        $contactPayload = $context['contact'] ?? null;
        if (!$contactPayload && !empty($context['contact_id'])) {
            $contact = \App\Models\Contact::find($context['contact_id']);
            if ($contact) {
                $contactPayload = [
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'whatsapp_user_id' => $contact->whatsapp_user_id,
                    'language_preferred' => $contact->language_preferred,
                ];
            }
        }
        $payload = [
            'contact' => $contactPayload,
            'channel_origin' => $context['channel_origin'] ?? 'system_event',
            'harbor_id' => $context['harbor_id'] ?? null,
            'boat_id' => $context['boat_id'] ?? null,
            'skip_auto_messages' => true,
            'reuse' => true,
        ];

        $request = $this->fakeRequest($context['ip'] ?? null);

        return $this->chatService->createConversation($payload, $request, $user);
    }

    private function resolveLocale(array $context, ?User $user): string
    {
        $candidate = $context['locale'] ?? null;
        if (!$candidate && $user?->preferred_locale) {
            $candidate = $user->preferred_locale;
        }
        if (!$candidate && !empty($context['contact_id'])) {
            $contact = \App\Models\Contact::find($context['contact_id']);
            $candidate = $contact?->language_preferred;
        }
        if (!$candidate && !empty($context['conversation_id'])) {
            $conversation = Conversation::find($context['conversation_id']);
            $candidate = $conversation?->language_preferred;
        }

        $candidate = strtolower((string) ($candidate ?: $this->locales->default()));
        if (!in_array($candidate, $this->locales->supported(), true)) {
            $candidate = $this->locales->default();
        }

        return $candidate;
    }

    private function fakeRequest(?string $ip = null): Request
    {
        $request = Request::create('/system/interaction', 'POST', []);
        $request->server->set('REMOTE_ADDR', $ip ?: '127.0.0.1');
        return $request;
    }
}
