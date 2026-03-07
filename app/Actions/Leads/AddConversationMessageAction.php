<?php

namespace App\Actions\Leads;

use App\Enums\RiskLevel;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use Illuminate\Auth\Access\AuthorizationException;

class AddConversationMessageAction
{
    public function __construct(
        private MessageRepository $messages,
        private NotificationDispatchService $notifications,
        private ActionSecurity $security
    ) {
    }

    public function execute(Conversation $conversation, array $data, ?User $actor = null): \App\Models\Message
    {
        if ($actor && $actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $senderType = $actor ? 'employee' : 'visitor';
        $employeeId = $actor?->id;

        if (! empty($data['client_message_id'])) {
            $existing = $this->messages->findByClientMessageId($conversation->id, $data['client_message_id']);
            if ($existing) {
                return $existing;
            }
        }

        $message = $this->messages->create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'employee_id' => $employeeId,
            'body' => $data['body'] ?? null,
            'client_message_id' => $data['client_message_id'] ?? null,
            'delivery_state' => $data['delivery_state'] ?? 'sent',
        ]);

        $lead = $conversation->lead;
        if ($lead) {
            $this->security->log('lead.message.created', RiskLevel::LOW, $actor, $lead, [
                'message_id' => $message->id,
            ], [
                'location_id' => $lead->location_id,
            ]);

            if ($senderType === 'visitor') {
                $this->notifyNewMessage($lead, $message);
            }
        }

        return $message;
    }

    private function notifyNewMessage(Lead $lead, \App\Models\Message $message): void
    {
        $staff = $lead->location?->users ?? [];

        $this->notifications->notifyUsers(
            $staff,
            'info',
            'New lead message',
            'A new lead message was received.',
            [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation_id,
                'message_id' => $message->id,
            ],
            null,
            true,
            true,
            $lead->location_id
        );
    }
}
