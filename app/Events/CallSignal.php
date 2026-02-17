<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public string $call_id,
        public int $from_user_id,
        public ?int $to_user_id,
        public string $signal_type, // offer | answer | ice
        public array $data,
        public ?string $type,
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('call.' . $this->call_id);
    }
    public function broadcastWith()
{
    return [
        'call_id' => $this->call_id,
        'from_user_id' => $this->from_user_id,
        'to_user_id' => $this->to_user_id,
        'signal_type' => $this->signal_type,
        'data' => $this->data,
        'type' => $this->type,
    ];
}

    public function broadcastAs()
    {
        return 'CallSignal';
    }
}