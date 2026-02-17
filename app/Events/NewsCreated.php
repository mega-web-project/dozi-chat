<?php

// app/Events/NewsCreated.php

namespace App\Events;

use App\Models\News;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NewsCreated implements ShouldBroadcast
{
    use SerializesModels;

    public $news;

    public function __construct(News $news)
    {
        $this->news = $news->load('user', 'poll.options');
    }

    public function broadcastOn()
    {
        return new Channel('news');
    }

    public function broadcastAs()
    {
        return 'news.created';
    }
}
