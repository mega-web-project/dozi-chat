<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NewConversation implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation)
    {
        // Load participants (User models) with pivot info
        $this->conversation = $conversation->load('participants');
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        // Broadcast on each participant's private channel
        return $this->conversation->participants->map(function ($participant) {
            return new Channel('user.' . $participant->id);
        })->toArray();
    }

    public function broadcastWith()
    {
        return [
            'conversation' => $this->conversation,
        ];
    }

    public function broadcastAs()
    {
        return 'conversation.new';
    }
}
