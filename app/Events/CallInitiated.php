<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CallInitiated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $caller_id;
    public $caller_name;
    public $participant_id;
    public $type;

    public function __construct($caller_id, $caller_name, $participant_id, $type)
    {
        $this->caller_id = $caller_id;
        $this->caller_name = $caller_name;
        $this->participant_id = $participant_id;
        $this->type = $type;
    }

    public function broadcastOn()
    {
        return new Channel('user.' . $this->participant_id);
    }

    public function broadcastAs()
    {
        return 'call.initiated';
    }
}
