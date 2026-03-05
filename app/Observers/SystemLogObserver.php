<?php

namespace App\Observers;

use App\Models\SystemLog;
use App\Services\InteractionEventService;

class SystemLogObserver
{
    public function created(SystemLog $log): void
    {
        $service = app(InteractionEventService::class);
        $service->record((string) $log->event_type, [
            'user_id' => $log->user_id,
            'metadata' => [
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'description' => $log->description,
                'old_data' => $log->old_data,
                'new_data' => $log->new_data,
                'changes' => $log->changes,
            ],
            'timestamp' => $log->created_at,
        ]);
    }
}
