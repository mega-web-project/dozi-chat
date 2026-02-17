<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PollVoted implements ShouldBroadcast
{
    use SerializesModels;

    public $poll;

    public function __construct($poll)
    {
        $this->poll = $poll->load('options');
    }

    public function broadcastOn()
    {
        return new Channel('news.' . $this->poll->news_id);
    }

    public function broadcastAs()
    {
        return 'poll.voted';
    }
}
