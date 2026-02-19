<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    \Log::info('Channel callback triggered', [
        'user_id' => $user->id ?? null,
        'conversationId' => $conversationId
    ]);

    $conversation = Conversation::find($conversationId);
    if (!$conversation) return false;

    $participantExists = $conversation->participants()
        ->where('user_id', $user->id)
        ->exists();

    if (!$participantExists) return false;

    return [
        'id' => $user->id,
        'name' => $user->name,
        // 'avatar' => $user->profile_photo_url, // optional
    ];
});



Broadcast::channel('call.{conversationId}', function ($user, $conversationId) {
    \Log::info('Channel callback triggered for presence', [
        'user_id' => $user->id ?? null,
        'conversationId' => $conversationId
    ]);

    $conversation = Conversation::find($conversationId);
    if (!$conversation) return false;

    $participantExists = $conversation->participants()
        ->where('user_id', $user->id)
        ->exists();

    if (!$participantExists) return false;

    return [
        'id' => $user->id,
        'name' => $user->name,
        // 'avatar' => $user->profile_photo_url, // optional
    ];
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
