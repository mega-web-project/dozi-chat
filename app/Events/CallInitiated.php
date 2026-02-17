<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CallInitiated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $caller_id;
    public $caller_name;
    public $participant_id;
    public $type;
    public $conversation_id;

    public function __construct($caller_id, $caller_name, $participant_id, $type, $conversation_id)
    {
        $this->caller_id = $caller_id;
        $this->caller_name = $caller_name;
        $this->participant_id = $participant_id;
        $this->type = $type;
        $this->conversation_id = $conversation_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->participant_id);
    }

    public function broadcastAs()
    {
        return 'call.initiated';
    }

    public function broadcastWith()
    {
        return [
            'caller_id' => $this->caller_id,
            'caller_name' => $this->caller_name,
            'type' => $this->type,
            'conversation_id' => $this->conversation_id,
        ];
    }
}
