<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionBidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $boatId,
        public array $payload
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel('auction.boat.' . $this->boatId)];
    }

    public function broadcastAs(): string
    {
        return 'auction.new_bid';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
