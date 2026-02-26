<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageMedia;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Events\MessageSent;
use App\Events\MessageUpdated;

class MessageController extends Controller
{

public function send(Request $request, Conversation $conversation)
{
    Log::info('[MessageSend] start', [
        'conversation_id' => $conversation->id,
        'user_id' => optional(Auth::user())->id,
        'content_length' => $request->header('content-length'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'has_media_key' => $request->has('media'),
        'has_file_media' => $request->hasFile('media'),
        'media_count' => is_array($request->file('media')) ? count($request->file('media')) : 0,
        'type' => $request->input('type'),
    ]);

    try {
        $request->validate([
            'type' => 'required|in:text,image,video,audio,file',
            'body' => 'nullable|string',
            'reply_to' => 'nullable|exists:messages,id',
            'media.*' => 'file|max:102400', // 100MB (KB units)
            'uploaded_media' => 'nullable|array',
            'uploaded_media.*.key' => 'required_with:uploaded_media|string|max:1024',
            'uploaded_media.*.file_type' => 'required_with:uploaded_media|string|max:255',
            'uploaded_media.*.file_size' => 'nullable|integer|min:1|max:104857600',
        ]);
        Log::info('[MessageSend] validation_passed');
    } catch (\Throwable $e) {
        Log::error('[MessageSend] validation_failed', [
            'message' => $e->getMessage(),
            'errors' => method_exists($e, 'errors') ? $e->errors() : null,
        ]);
        throw $e;
    }

    $user = Auth::user();

    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        Log::warning('[MessageSend] user_not_participant', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'type' => $request->type,
        'body' => $request->body,
        'reply_to' => $request->reply_to,
    ]);

    Log::info('[MessageSend] message_created', ['message_id' => $message->id]);

    if ($request->hasFile('media')) {
        foreach ($request->file('media') as $idx => $file) {
            Log::info('[MessageSend] processing_file', [
                'index' => $idx,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'upload_error_code' => $file->getError(),
                'upload_error_message' => $file->getErrorMessage(),
                'is_valid' => $file->isValid(),
            ]);

            try {
                $path = $file->store('messages', 's3');

                if (!$path) {
                    Log::error('[MessageSend] file_store_returned_empty', ['index' => $idx]);
                    continue;
                }

                $url = Storage::disk('s3')->url($path);

                MessageMedia::create([
                    'message_id' => $message->id,
                    'file_url' => $url,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                Log::info('[MessageSend] file_saved', [
                    'index' => $idx,
                    'path' => $path,
                    'url' => $url,
                ]);
            } catch (\Throwable $e) {
                Log::error('[MessageSend] file_store_failed', [
                    'index' => $idx,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }
    } else {
        Log::warning('[MessageSend] no_files_detected_after_validation');
    }

    if (is_array($request->input('uploaded_media'))) {
        $allowedPrefix = "messages/{$conversation->id}/{$user->id}/";

        foreach ($request->input('uploaded_media') as $idx => $media) {
            $key = ltrim((string) ($media['key'] ?? ''), '/');

            if (!str_starts_with($key, $allowedPrefix)) {
                throw ValidationException::withMessages([
                    "uploaded_media.$idx.key" => ['Invalid media key prefix.'],
                ]);
            }

            if (!Storage::disk('s3')->exists($key)) {
                throw ValidationException::withMessages([
                    "uploaded_media.$idx.key" => ['Uploaded file does not exist on storage.'],
                ]);
            }

            MessageMedia::create([
                'message_id' => $message->id,
                'file_url' => Storage::disk('s3')->url($key),
                'file_type' => (string) ($media['file_type'] ?? 'application/octet-stream'),
                'file_size' => $media['file_size'] ?? null,
            ]);
        }
    }

    broadcast(new MessageSent($message->load('media', 'sender')))->toOthers();
    $this->sendPushNotifications($conversation, $message, $user);

    $unread_count = $conversation->messages()
        ->where('sender_id', '!=', $user->id)
        ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
        ->count();

    Log::info('[MessageSend] completed', [
        'message_id' => $message->id,
        'media_count' => $message->media()->count(),
        'unread_count' => $unread_count,
    ]);

    return response()->json([
        'message' => 'Message sent successfully',
        'data' => $message->load('media', 'sender'),
        'unread_count' => $unread_count,
    ], 201);
}

public function generateUploadUrl(Request $request, Conversation $conversation)
{
    $request->validate([
        'file_name' => 'required|string|max:255',
        'file_type' => 'required|string|max:255',
        'file_size' => 'required|integer|min:1|max:104857600', // 100MB bytes
    ]);

    $user = Auth::user();

    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    $extension = pathinfo($request->input('file_name'), PATHINFO_EXTENSION);
    $filename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
    $key = "messages/{$conversation->id}/{$user->id}/{$filename}";

    $upload = Storage::disk('s3')->temporaryUploadUrl(
        $key,
        now()->addMinutes(10),
        ['ContentType' => $request->input('file_type')]
    );

    $forbiddenBrowserHeaders = ['host', 'content-length'];
    $headers = collect($upload['headers'] ?? [])
        ->mapWithKeys(function ($value, $key) {
            return [$key => is_array($value) ? implode(', ', $value) : $value];
        })
        ->reject(function ($value, $key) use ($forbiddenBrowserHeaders) {
            return in_array(strtolower((string) $key), $forbiddenBrowserHeaders, true);
        })
        ->all();

    return response()->json([
        'message' => 'Pre-signed upload URL generated',
        'data' => [
            'upload_url' => $upload['url'],
            'upload_headers' => $headers,
            'upload_method' => 'PUT',
            'key' => $key,
            'file_url' => Storage::disk('s3')->url($key),
            'expires_at' => now()->addMinutes(10)->toISOString(),
            'max_size_bytes' => 104857600,
        ],
    ]);
}

public function update(Request $request, Message $message)
{
    $request->validate([
        'body' => 'required|string',
    ]);

    $user = Auth::user();

    if (!$message->conversation->participants()->where('user_id', $user->id)->exists()) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    if ((int) $message->sender_id !== (int) $user->id) {
        throw ValidationException::withMessages([
            'message' => ['You can only edit your own messages'],
        ]);
    }

    if ($message->type === 'system') {
        throw ValidationException::withMessages([
            'message' => ['System messages cannot be edited'],
        ]);
    }

    $message->body = $request->input('body');
    $message->is_edited = true; // requires DB column
    $message->save();

    $message->load('media', 'sender');

    broadcast(new MessageUpdated($message))->toOthers();

    return response()->json([
        'message' => 'Message updated successfully',
        'data' => $message,
    ]);
}


protected function sendPushNotifications(Conversation $conversation, Message $message, $sender): void
{
    // Keep API send flow resilient when push is not configured yet.
    if (!Schema::hasColumn('users', 'fcm_token')) {
        return;
    }

    $serverKey = env('FCM_SERVER_KEY');

    if (empty($serverKey)) {
        return;
    }

    $tokens = $conversation->participants()
        ->where('users.id', '!=', $sender->id)
        ->wherePivotNull('left_at')
        ->whereNotNull('users.fcm_token')
        ->pluck('users.fcm_token')
        ->filter()
        ->unique()
        ->values();

    if ($tokens->isEmpty()) {
        return;
    }

    $body = $message->type === 'text'
        ? (string) ($message->body ?? 'New message')
        : 'Sent an attachment';

    foreach ($tokens as $token) {
        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $sender->name,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'sender_id' => $sender->id,
                    'type' => $message->type,
                ],
            ])->throw();
        } catch (\Throwable $e) {
            Log::warning('Push notification send failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }
    }
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
