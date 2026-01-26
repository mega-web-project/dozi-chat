<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class UserOnlineStatus implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public int $userId,
        public bool $online
    ) {}

    public function broadcastOn()
    {
        return new Channel('presence');
    }

    public function broadcastAs()
    {
        return 'user.presence';
    }
}
