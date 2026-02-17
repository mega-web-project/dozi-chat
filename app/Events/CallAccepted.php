<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class CallAccepted implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public string $call_id,
        public int $accepter_id,
        public int $caller_id
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->caller_id);
    }

    public function broadcastAs()
    {
        return 'CallAccepted';
    }
}
