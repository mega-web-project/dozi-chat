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
use App\Events\TypingIndicator;
use App\Events\ParticipantRemoved;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{

public function AllUsers()
{
    $authUser = auth()->user();

    $users = User::where('id', '!=', $authUser->id)->get();

    return response()->json([
        'users' => $users
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
                'avatar' => $contact->avatar
                    ? Storage::disk('s3')->url($contact->avatar)
                    : null,

                'last_seen_at' => $contact->last_seen_at,
                'availability' => $contact->availability,
                'do_not_disturb' => $contact->do_not_disturb,
                // Chat data
                'has_conversation' => (bool) $conversation,
                'conversation_id' => $conversation?->id,
                'unread_count' => $conversation?->unread_count ?? 0,
                'role'=>$contact->role,

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
            'conversation_id' => 'nullable|exists:conversations,id',
            'type' => 'required_without:conversation_id|in:private,group',
            'title' => 'nullable|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'group_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'group_banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'group_description' => 'nullable|string',
            'participant_ids' => 'required_without:conversation_id|array|min:1',
            'participant_ids.*' => 'exists:users,id',
            'group_settings' => 'nullable|array',
            'group_settings.who_can_send_messages' => 'sometimes|in:all,admins',
            'group_settings.who_can_edit_info' => 'sometimes|in:all,admins',
            'group_settings.allow_member_invite' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $isUpdate = $request->filled('conversation_id');
        $groupLogoUrl = null;
        $groupBannerUrl = null;

        if ($isUpdate) {
            $conversation = Conversation::with('participants', 'groupSetting')->findOrFail($request->conversation_id);

            $isAdmin = $conversation->participants()
                ->where('user_id', $user->id)
                ->wherePivot('role', 'admin')
                ->exists();

            if (!$isAdmin && $conversation->created_by !== $user->id) {
                throw ValidationException::withMessages([
                    'conversation' => ['You are not allowed to update this conversation'],
                ]);
            }
        } else {
            $conversation = null;
        }

        if ($request->hasFile('group_logo')) {
            $logoPath = $request->file('group_logo')->store('groups/logos', 's3');
            $groupLogoUrl = Storage::disk('s3')->url($logoPath);
        }

        if ($request->hasFile('group_banner')) {
            $bannerPath = $request->file('group_banner')->store('groups/banners', 's3');
            $groupBannerUrl = Storage::disk('s3')->url($bannerPath);
        }

        $conversationPayload = [
            'title' => $request->title ?? $request->group_name,
            'group_name' => $request->group_name,
            'group_description' => $request->group_description,
        ];

        if ($request->filled('type')) {
            $conversationPayload['type'] = $request->type;
        }

        if ($groupLogoUrl !== null) {
            $conversationPayload['group_logo'] = $groupLogoUrl;
        }

        if ($groupBannerUrl !== null) {
            $conversationPayload['group_banner'] = $groupBannerUrl;
        }

        if ($isUpdate) {
            $conversation->update($conversationPayload);
        } else {
            $conversationPayload['created_by'] = $user->id;
            $conversation = Conversation::create($conversationPayload);

            // Add creator as admin
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add other participants
            foreach ($request->participant_ids as $pid) {
                if ((int) $pid === (int) $user->id) {
                    continue;
                }

                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $pid,
                    ],
                    [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]
                );
            }
        }

        if ($isUpdate && $request->filled('participant_ids')) {
            foreach ($request->participant_ids as $pid) {
                if ((int) $pid === (int) $user->id) {
                    continue;
                }

                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $pid,
                    ],
                    [
                        'role' => 'member',
                        'joined_at' => now(),
                    ]
                );
            }
        }

        $effectiveType = $conversation->type;

        if ($effectiveType === 'group') {
            $groupSettings = $request->input('group_settings', []);

            $conversation->groupSetting()->updateOrCreate([
                'conversation_id' => $conversation->id,
            ], [
                'who_can_send_messages' => $groupSettings['who_can_send_messages'] ?? 'all',
                'who_can_edit_info' => $groupSettings['who_can_edit_info'] ?? 'admins',
                'allow_member_invite' => $groupSettings['allow_member_invite'] ?? false,
            ]);
        }

        if (!$isUpdate) {
            broadcast(new NewConversation($conversation))->toOthers();
        }

        return response()->json([
            'message' => $isUpdate
                ? 'Conversation updated successfully'
                : 'Conversation created successfully',
            'conversation' => $conversation->load('participants', 'groupSetting'),
        ], $isUpdate ? 200 : 201);
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

//     public function typing(Request $request, Conversation $conversation)
// {
//     $request->validate([
//         'is_typing' => 'required|boolean',
//     ]);

//     $user = auth()->user();

//     if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
//         return response()->json([
//             'message' => 'You are not a participant in this conversation'
//         ], 403);
//     }

//     broadcast(new \App\Events\TypingIndicator(
//         $conversation->id,
//         $user->id,
//         $user->name,
//         $request->is_typing
//     ))->toOthers();

//     return response()->json([
//         'message' => 'Typing event sent'
//     ]);
// }


public function typing(Request $request, Conversation $conversation)
{
    $request->validate([
        'is_typing' => 'nullable|boolean',
    ]);

    $user = Auth::user();

    // Ensure sender belongs to conversation
    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
        throw ValidationException::withMessages([
            'conversation' => ['You are not a participant in this conversation'],
        ]);
    }

    $isTyping = $request->boolean('is_typing', true);

    broadcast(new TypingIndicator(
        $conversation->id,
        $user->id,
        $user->name,
        $isTyping
    ))->toOthers();

    return response()->json([
        'message' => 'Typing event sent',
    ]);
}

}
