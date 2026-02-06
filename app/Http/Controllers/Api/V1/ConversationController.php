<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Events\NewConversation;
use App\Events\ParticipantAdded;
use App\Events\ParticipantRemoved;

class ConversationController extends Controller
{
    // get all contacts 

// get all users in the system
public function AllUsers() {
    $authUser = auth()->user();

    // 1️⃣ Get IDs of users who already have a conversation with the auth user
    $conversationUserIds = Conversation::where('type', 'private')
        ->whereHas('participants', fn($q) => $q->where('user_id', $authUser->id))
        ->with('participants')
        ->get()
        ->flatMap(fn($conversation) => $conversation->participants->pluck('id'))
        ->unique()
        ->filter(fn($id) => $id != $authUser->id); // exclude self

    // 2️⃣ Get all users except self and except those in a conversation
    $users = User::where('id', '!=', $authUser->id)
        ->whereNotIn('id', $conversationUserIds)
        ->get();

    return response()->json([
       
        'users' => $users->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar'=>$user->avatar,
            'email'=>$user->email
        ];
    }),
    ]);
}


public function contacts()
{
    $user = auth()->user();

    // 1️⃣ Load all private conversations involving the user
    $conversations = Conversation::where('type', 'private')
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->with([
            'participants',
            'messages' => fn ($q) => $q->latest()->limit(1), // last message
        ])
        ->withCount([
            'messages as unread_count' => function ($q) use ($user) {
                $q->whereDoesntHave('reads', fn ($r) =>
                    $r->where('user_id', $user->id)
                      ->whereNotNull('read_at')
                )
                ->where('sender_id', '!=', $user->id);
            }
        ])
        ->get()
        ->keyBy(fn ($conversation) => 
            $conversation->participants->first(fn($p) => $p->id !== $user->id)?->id
        );

    // 2️⃣ Fetch all users except self
    $contacts = User::where('id', '!=', $user->id)
        ->get()
        ->map(function ($contact) use ($conversations) {
            $conversation = $conversations->get($contact->id);

            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'avatar' => $contact->avatar,
                'last_seen_at' => $contact->last_seen_at,
                'availability' => $contact->availability,
                'do_not_disturb' => $contact->do_not_disturb,

                // Chat data
                'has_conversation' => (bool) $conversation,
                'conversation_id' => $conversation?->id,
                'unread_count' => $conversation?->unread_count ?? 0,

                'last_message' => $conversation?->messages->first() ? [
                    'body' => $conversation->messages->first()->body,
                    'created_at' => $conversation->messages->first()->created_at,
                ] : null,
            ];
        })
        ->filter(fn($contact) => $contact['has_conversation']) // Only keep users with a conversation
        ->values(); // Reindex the collection

    return response()->json([
        'contacts' => $contacts,
    ]);
}




    /**
     * List all conversations for the authenticated user
     */
    public function index()
    {
        $user = Auth::user();

        $conversations = $user->conversations()
                              ->with(['participants', 'messages' => function ($q) {
                                  $q->latest()->limit(1);
                              }])
                              ->latest()
                              ->get();

        return response()->json($conversations);
    }

    /**
     * Create a new conversation
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:private,group',
            'title' => 'nullable|string|max:255',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $user = Auth::user();

        $conversation = Conversation::create([
            'type' => $request->type,
            'title' => $request->title,
            'created_by' => $user->id,
        ]);

        // Add creator as admin
        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        // Add other participants
        foreach ($request->participant_ids as $pid) {
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $pid,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        broadcast(new NewConversation($conversation))->toOthers();

        return response()->json([
            'message' => 'Conversation created successfully',
            'conversation' => $conversation->load('participants'),
        ], 201);
    }

    public function privateChat(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $auth = auth()->user();
    $other = $request->user_id;

    if ($auth->id === (int) $other) {
        return response()->json(['message' => 'Invalid user'], 422);
    }

    // Check existing conversation
    $conversation = Conversation::where('type', 'private')
        ->whereHas('participants', fn ($q) => $q->where('user_id', $auth->id))
        ->whereHas('participants', fn ($q) => $q->where('user_id', $other))
        ->first();

    if ($conversation) {
        return response()->json($conversation->load('participants'));
    }

    // Create new conversation
    $conversation = Conversation::create([
        'type' => 'private',
        'created_by' => $auth->id,
    ]);

    ConversationParticipant::insert([
        [
            'conversation_id' => $conversation->id,
            'user_id' => $auth->id,
            'role' => 'admin',
            'joined_at' => now(),
        ],
        [
            'conversation_id' => $conversation->id,
            'user_id' => $other,
            'role' => 'member',
            'joined_at' => now(),
        ],
    ]);

    broadcast(new NewConversation($conversation));

    return response()->json($conversation->load('participants'), 201);
}

    /**
     * Add a participant
     */
    public function addParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'in:admin,member',
        ]);

        if ($conversation->participants()->where('user_id', $request->user_id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['User is already a participant'],
            ]);
        }

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user_id,
            'role' => $request->role ?? 'member',
            'joined_at' => now(),
        ]);

        broadcast(new ParticipantAdded($conversation, $request->user_id))->toOthers();

        return response()->json([
            'message' => 'Participant added successfully',
            'conversation' => $conversation->load('participants'),
        ]);
    }

    /**
     * Remove a participant
     */
    public function removeParticipant(Conversation $conversation, $user_id)
    {
        $conversation->participants()->detach($user_id);

        broadcast(new ParticipantRemoved($conversation, $user_id))->toOthers();

        return response()->json([
            'message' => 'Participant removed successfully',
            'conversation' => $conversation->load('participants'),
        ]);
    }

    public function typing(Request $request, Conversation $conversation)
{
    $request->validate([
        'is_typing' => 'required|boolean',
    ]);

    $user = auth()->user();

    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        return response()->json([
            'message' => 'You are not a participant in this conversation'
        ], 403);
    }

    broadcast(new \App\Events\TypingIndicator(
        $conversation->id,
        $user->id,
        $user->name,
        $request->is_typing
    ))->toOthers();

    return response()->json([
        'message' => 'Typing event sent'
    ]);
}

}
