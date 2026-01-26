<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ParticipantRemoved implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $conversation;
    public $user_id;

    public function __construct(Conversation $conversation, $user_id)
    {
        $this->conversation = $conversation->load('participants.user');
        $this->user_id = $user_id;
    }

    public function broadcastOn()
    {
        return [
            new Channel('user.' . $this->user_id),
            new Channel('conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'conversation' => $this->conversation,
            'user_id' => $this->user_id,
        ];
    }

    public function broadcastAs()
    {
        return 'conversation.participant.removed';
    }
}
