<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageMedia;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Events\MessageSent;

class MessageController extends Controller
{
    /**
     * Send a message in a conversation
     */
    // public function send(Request $request, Conversation $conversation)
    // {
    //     $request->validate([
    //         'type' => 'required|in:text,image,video,audio,file',
    //         'body' => 'nullable|string',
    //         'reply_to' => 'nullable|exists:messages,id',
    //         'media.*' => 'file|max:10240', // max 10MB
    //     ]);

    //     $user = Auth::user();

    //     // Only allow participants
    //     if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
    //         throw ValidationException::withMessages([
    //             'conversation' => ['You are not a participant in this conversation'],
    //         ]);
    //     }

    //     // Create message
    //     $message = Message::create([
    //         'conversation_id' => $conversation->id,
    //         'sender_id' => $user->id,
    //         'type' => $request->type,
    //         'body' => $request->body,
    //         'reply_to' => $request->reply_to,
    //     ]);

    //     // Handle media files
    //     if ($request->hasFile('media')) {
    //         foreach ($request->file('media') as $file) {
    //             $path = $file->store('messages', 'public');

    //             MessageMedia::create([
    //                 'message_id' => $message->id,
    //                 'file_url' => Storage::url($path),
    //                 'file_type' => $file->getClientMimeType(),
    //                 'file_size' => $file->getSize(),
    //             ]);
    //         }
    //     }

    //     // Broadcast event
    //     broadcast(new MessageSent($message->load('media', 'sender')))->toOthers();

    //     return response()->json([
    //         'message' => 'Message sent successfully',
    //         'data' => $message->load('media', 'sender'),
    //     ], 201);
    // }

    public function send(Request $request, Conversation $conversation)
{
    $request->validate([
        'type' => 'required|in:text,image,video,audio,file',
        'body' => 'nullable|string',
        'reply_to' => 'nullable|exists:messages,id',
        'media.*' => 'file|max:10240', // max 10MB
    ]);

    $user = Auth::user();

    // Only allow participants
    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    // Create message
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'type' => $request->type,
        'body' => $request->body,
        'reply_to' => $request->reply_to,
    ]);

    // Handle media files
    if ($request->hasFile('media')) {
        foreach ($request->file('media') as $file) {
            $path = $file->store('messages', 'public');

            MessageMedia::create([
                'message_id' => $message->id,
                'file_url' => Storage::url($path),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    // Broadcast event
    broadcast(new MessageSent($message->load('media', 'sender')))->toOthers();

    // Calculate unread count for the conversation for this user
    // Unread = messages in this conversation not read by this user
    $unread_count = $conversation->messages()
        ->where('sender_id', '!=', $user->id)
        ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
        ->count();

    return response()->json([
        'message' => 'Message sent successfully',
        'data' => $message->load('media', 'sender'),
        'unread_count' => $unread_count,
    ], 201);
}

    /**
     * Mark message as read
     */
   public function markRead(Request $request, Message $message)
{
    $user = Auth::user();

    // Ensure user is part of the conversation
    if (
        !$message->conversation
            ->participants()
            ->where('user_id', $user->id)
            ->exists()
    ) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    $message->reads()->updateOrCreate(
        [
            'message_id' => $message->id,
            'user_id' => $user->id,
        ],
        [
            'read_at' => now(),
        ]
    );

    return response()->json([
        'message' => 'Message marked as read',
    ]);
    }

    /**
     * List messages in a conversation
     */
    // public function index(Request $request, Conversation $conversation)
    // {
    //     $user = Auth::user();

    //     if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
    //         throw ValidationException::withMessages([
    //             'conversation' => ['You are not a participant in this conversation'],
    //         ]);
    //     }

    //     $messages = $conversation->messages()
    //                              ->with(['sender', 'media', 'reads'])
    //                              ->latest()
    //                              ->paginate(50);

    //     return response()->json($messages);
    // }

    public function index(Request $request, Conversation $conversation)
{
    $user = Auth::user();

    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    $isGroup = $conversation->participants()->count() > 2;

    $messages = $conversation->messages()
        ->with(['sender', 'media', 'reads'])
        ->latest()
        ->paginate(50);

    $messages->getCollection()->transform(function ($message) use ($user, $isGroup) {
        $isMe = $message->sender_id === $user->id;

        return [
            'id'         => $message->id,
            'body'       => $message->body,
            'created_at' => $message->created_at,
            'media'      => $message->media,
            'reads'      => $message->reads,

            // identity
            'identity'   => $isMe ? 'sender' : 'receiver',
            'is_me'      => $isMe,
            'is_group'   => $isGroup,

            // sender info (critical for group chat)
            'sender' => [
                'id'     => $message->sender->id,
                'name'   => $message->sender->name,
                'avatar' => $message->sender->avatar ?? null,
            ],
        ];
    });

    return response()->json($messages);
}

}
