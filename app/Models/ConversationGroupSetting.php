<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationGroupSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'who_can_send_messages',
        'who_can_edit_info',
        'allow_member_invite',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
