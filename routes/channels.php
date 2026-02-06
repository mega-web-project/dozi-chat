<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Only allow participants in the conversation
    return $user->conversations()->where('id', $conversationId)->exists();
});
