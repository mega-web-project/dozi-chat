<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'created_by',
    ];

    // Creator of conversation
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Participants
    public function participants()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_participants'
        )->withPivot(['role', 'joined_at', 'left_at']);
    }

    // Messages in conversation
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
{
    return $this->hasOne(Message::class)->latestOfMany();
}


public function typing(Request $request, Conversation $conversation)
{
    $request->validate([
        'is_typing' => 'required|boolean',
    ]);

    $user = auth()->user();

    // Make sure user is in the conversation
    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'Not a participant'], 403);
    }

    broadcast(new \App\Events\TypingIndicator(
        $conversation->id,
        $user->id,
        $user->name,
        $request->is_typing
    ))->toOthers();

    return response()->json(['message' => 'Typing status sent']);
}

}
