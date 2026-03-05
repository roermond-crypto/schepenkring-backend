<?php

namespace App\Services;

use App\Models\InteractionEventType;
use App\Models\InteractionTimelineEntry;

class InteractionEventService
{
    public function __construct(
        private InteractionHubService $hub,
        private InteractionRouterService $router
    ) {
    }

    public function record(string $eventKey, array $context = []): InteractionTimelineEntry
    {
        $eventType = InteractionEventType::where('key', $eventKey)->first();

        $entry = InteractionTimelineEntry::create([
            'user_id' => $context['user_id'] ?? null,
            'contact_id' => $context['contact_id'] ?? null,
            'conversation_id' => $context['conversation_id'] ?? null,
            'event_type_id' => $eventType?->id,
            'channel' => 'system',
            'direction' => 'system',
            'title' => $eventKey,
            'body' => $context['message'] ?? null,
            'metadata' => $context['metadata'] ?? $context,
            'occurred_at' => $context['timestamp'] ?? now(),
        ]);

        $this->router->dispatchEvent($eventKey, $context);

        return $entry;
    }
}
