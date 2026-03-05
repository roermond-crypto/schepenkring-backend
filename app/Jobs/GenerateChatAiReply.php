<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatAiService;
use App\Services\IssueReportService;
use App\Services\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateChatAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $conversationId, public string $messageId)
    {
    }

    public function handle(ChatAiService $ai, NotificationDispatchService $notifier, IssueReportService $reports): void
    {
        $conversation = Conversation::with('contact')->find($this->conversationId);
        $message = Message::find($this->messageId);

        if (!$conversation || !$message) {
            return;
        }

        if ($conversation->status === 'solved') {
            return;
        }

        if ($conversation->last_staff_message_at && $conversation->last_staff_message_at->gt($message->created_at)) {
            return;
        }

        $language = $message->language ?? $conversation->language_preferred ?? 'en';
        $result = $ai->generateReply($conversation, $message->text ?? '', $language);

        if ($result['status'] === 'low') {
            $this->maybeReportIssue($conversation, $message, $reports);
            $conversation->priority = 'high';
            $conversation->save();
            $this->logEvent($conversation, 'ai_escalated', $result);
            $this->notifyEscalation($notifier, $conversation);
            return;
        }

        if ($conversation->ai_mode === 'assist') {
            $this->logEvent($conversation, 'ai_draft', [
                'confidence' => $result['confidence'] ?? null,
                'draft' => $result['answer'],
            ]);
            return;
        }

        $aiMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'text' => $result['answer'],
            'language' => $language,
            'channel' => 'web',
            'message_type' => 'text',
            'ai_confidence' => $result['confidence'] ?? null,
            'metadata' => [
                'sources' => $result['sources'] ?? [],
            ],
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        $this->logEvent($conversation, 'ai_reply', [
            'message_id' => $aiMessage->id,
            'confidence' => $result['confidence'] ?? null,
        ]);
    }

    private function logEvent(Conversation $conversation, string $type, array $payload): void
    {
        $conversation->events()->create([
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    private function notifyEscalation(NotificationDispatchService $notifier, Conversation $conversation): void
    {
        $admins = \App\Models\User::where('role', 'Admin')->where('status', 'Active')->get();
        foreach ($admins as $admin) {
            $notifier->notifyUser(
                $admin,
                'chat_escalation',
                'Chat Escalation',
                'AI could not confidently answer a chat message.',
                ['conversation_id' => $conversation->id],
                null,
                true,
                true
            );
        }
    }

    private function maybeReportIssue(Conversation $conversation, Message $message, IssueReportService $reports): void
    {
        if (!$message->text) {
            return;
        }

        $already = \App\Models\IssueReport::where('message_id', $message->id)->exists();
        if ($already) {
            return;
        }

        $payload = [
            'subject' => 'Chat escalation - user reported issue',
            'description' => $message->text,
            'page_url' => $conversation->page_url,
            'email' => $conversation->contact?->email,
            'source' => 'chat',
            'language' => $message->language ?? $conversation->language_preferred,
            'metadata' => [
                'conversation_id' => $conversation->id,
                'channel_origin' => $conversation->channel_origin,
            ],
        ];

        $reports->create($payload, request(), null, $conversation, $message);
    }
}
