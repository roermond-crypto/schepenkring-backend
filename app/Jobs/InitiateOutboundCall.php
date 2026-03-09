<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\PhoneCallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitiateOutboundCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $messageId)
    {
    }

    public function handle(PhoneCallService $phoneService): void
    {
        $message = Message::with('conversation.contact')->find($this->messageId);
        if (! $message) {
            return;
        }

        $phoneService->initiateOutboundCall($message);
    }
}
