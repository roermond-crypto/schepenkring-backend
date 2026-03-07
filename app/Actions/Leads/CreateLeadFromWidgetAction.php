<?php

namespace App\Actions\Leads;

use App\Enums\RiskLevel;
use App\Jobs\EnrichLeadJob;
use App\Models\Lead;
use App\Models\Location;
use App\Repositories\ConversationRepository;
use App\Repositories\LeadRepository;
use App\Repositories\MessageRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

class CreateLeadFromWidgetAction
{
    public function __construct(
        private ConversationRepository $conversations,
        private LeadRepository $leads,
        private MessageRepository $messages,
        private NotificationDispatchService $notifications,
        private ActionSecurity $security
    ) {
    }

    /**
     * @return array{lead:Lead, conversation:\App\Models\Conversation, message:? \App\Models\Message}
     */
    public function execute(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            $conversation = $this->conversations->create([
                'location_id' => $data['location_id'],
                'channel' => 'web_widget',
                'status' => 'open',
                'assigned_employee_id' => null,
            ]);

            $lead = $this->leads->create([
                'conversation_id' => $conversation->id,
                'location_id' => $data['location_id'],
                'status' => 'new',
                'assigned_employee_id' => null,
                'source_url' => $data['source_url'],
                'referrer' => $data['referrer'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'utm_term' => $data['utm_term'] ?? null,
                'utm_content' => $data['utm_content'] ?? null,
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? null,
            ]);

            $this->conversations->update($conversation, [
                'lead_id' => $lead->id,
            ]);

            $message = null;
            if (! empty($data['message'])) {
                $message = $this->messages->create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'visitor',
                    'employee_id' => null,
                    'body' => $data['message'],
                    'client_message_id' => $data['client_message_id'] ?? null,
                    'delivery_state' => $data['delivery_state'] ?? 'sent',
                ]);
            }

            return [
                'lead' => $lead,
                'conversation' => $conversation,
                'message' => $message,
            ];
        });

        $lead = $result['lead'];
        $message = $result['message'];

        $this->security->log('lead.created', RiskLevel::LOW, null, $lead, [], [
            'location_id' => $lead->location_id,
            'snapshot_after' => $lead->toArray(),
        ]);

        if ($message) {
            $this->security->log('lead.message.created', RiskLevel::LOW, null, $lead, [
                'message_id' => $message->id,
            ], [
                'location_id' => $lead->location_id,
            ]);
        }

        $this->notifyNewLead($lead, $message);

        if ($this->shouldEnrich($lead)) {
            EnrichLeadJob::dispatch($lead->id);
        }

        return $result;
    }

    private function notifyNewLead(Lead $lead, ?\App\Models\Message $message): void
    {
        $location = Location::find($lead->location_id);
        $staff = $location?->users ?? [];

        $this->notifications->notifyUsers(
            $staff,
            'info',
            'New lead',
            'A new lead has been created.',
            [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation_id,
            ],
            null,
            true,
            true,
            $lead->location_id
        );

        if ($message) {
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

    private function shouldEnrich(Lead $lead): bool
    {
        return (bool) ($lead->address_line1 || $lead->city || $lead->postal_code || $lead->country);
    }
}
