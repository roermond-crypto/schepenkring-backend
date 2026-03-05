<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCountUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $count,
        public ?int $locationId = null
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('user.'.$this->userId)];

        if ($this->locationId) {
            $channels[] = new PrivateChannel('location.'.$this->locationId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.count.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'count' => $this->count,
        ];
    }
}
