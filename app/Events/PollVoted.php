<?php

namespace App\Events;

use App\Models\Poll;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PollVoted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Poll $poll;

    public function __construct(Poll $poll)
    {
        $this->poll = $poll->load('options');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('news');
    }

    public function broadcastAs(): string
    {
        return 'news.poll.voted';
    }

    public function broadcastWith(): array
    {
        return [
            'post_id' => (string) $this->poll->news_id,
            'poll' => $this->poll,
        ];
    }
}
