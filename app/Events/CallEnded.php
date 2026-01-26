<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CallEnded implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $caller_id;
    public $participant_id;

    public function __construct($caller_id, $participant_id)
    {
        $this->caller_id = $caller_id;
        $this->participant_id = $participant_id;
    }

    public function broadcastOn()
    {
        return new Channel('user.' . $this->participant_id);
    }

    public function broadcastAs()
    {
        return 'call.ended';
    }
}
