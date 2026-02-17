<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;

        Log::info('MessageSent constructor fired', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
        ]);
    }

  public function broadcastOn()
{
    $userIds = $this->message
        ->conversation
        ->participants
        ->pluck('id') // ✅ THIS IS THE FIX
        ->toArray();

    \Log::info('MessageSent broadcasting on channels', [
        'channels' => array_map(fn ($id) => "user.$id", $userIds),
    ]);

    return collect($userIds)
        ->map(fn ($id) => new Channel("user.$id"))
        ->toArray();
}


    public function broadcastWith()
    {
        return [
            'message' => $this->message->toArray(),
        ];
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }
}