<?php

namespace App\Listeners;

use App\Events\BoatStatusActivated;
use App\Jobs\AutoGenerateAiVideoJob;
use Illuminate\Support\Facades\Log;

class TriggerAiVideoGeneration
{
    public function handle(BoatStatusActivated $event): void
    {
        $yacht = $event->entity;

        if (!$yacht || !$yacht->id) {
            return;
        }

        Log::info("TriggerAiVideoGeneration: dispatching AutoGenerateAiVideoJob for yacht #{$yacht->id}");

        // Delay 2 minutes to allow image processing jobs to complete first
        AutoGenerateAiVideoJob::dispatch($yacht->id)->delay(now()->addMinutes(2));
    }
}
