<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public UserNotification $userNotification,
        public int $unreadCount,
        public ?int $locationId = null
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('user.'.$this->userNotification->user_id)];

        if ($this->locationId) {
            $channels[] = new PrivateChannel('location.'.$this->locationId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $notification = $this->userNotification->loadMissing('notification');

        return [
            'notification' => $notification->toArray(),
            'unread_count' => $this->unreadCount,
        ];
    }
}
