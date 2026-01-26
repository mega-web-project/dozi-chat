<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'reply_to',
    ];

    // Sender
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Conversation
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    // Replying to another message
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    // Read receipts
    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    // Media attachments
    public function media()
    {
        return $this->hasMany(MessageMedia::class);
    }
}
