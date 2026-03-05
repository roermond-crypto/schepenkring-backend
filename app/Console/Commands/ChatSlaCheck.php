<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\ChatConversationService;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;

class ChatSlaCheck extends Command
{
    protected $signature = 'chat:check-sla';
    protected $description = 'Check chat SLA and escalate overdue conversations';

    public function handle(ChatConversationService $service, NotificationDispatchService $notifier): int
    {
        $overdue = Conversation::whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('last_staff_message_at')
                      ->orWhereColumn('last_staff_message_at', '<', 'last_customer_message_at');
            })
            ->whereIn('status', ['open', 'pending'])
            ->get();

        $escalateHarborId = (int) env('CHAT_ESCALATE_TO_HARBOR_ID', 1);

        foreach ($overdue as $conversation) {
            $conversation->priority = 'high';
            if ($escalateHarborId) {
                $conversation->harbor_id = $escalateHarborId;
            }
            $conversation->save();

            $service->recordEvent($conversation, 'sla_escalated', [
                'harbor_id' => $conversation->harbor_id,
            ]);

            $admins = \App\Models\User::where('role', 'Admin')->where('status', 'Active')->get();
            foreach ($admins as $admin) {
                $notifier->notifyUser(
                    $admin,
                    'chat_sla_escalation',
                    'Chat SLA Escalation',
                    'A conversation missed the first-response SLA.',
                    ['conversation_id' => $conversation->id],
                    null,
                    true,
                    true
                );
            }
        }

        $this->info('SLA check completed. Escalated: ' . $overdue->count());

        return self::SUCCESS;
    }
}
