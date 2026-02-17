<?php

class CommentAdded implements ShouldBroadcast
{
    use SerializesModels;

    public $comment;

    public function __construct($comment)
    {
        $this->comment = $comment->load('user');
    }

    public function broadcastOn()
    {
        return new Channel('news.' . $this->comment->news_id);
    }

    public function broadcastAs()
    {
        return 'comment.added';
    }
}
