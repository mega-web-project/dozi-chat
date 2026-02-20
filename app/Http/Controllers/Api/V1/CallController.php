<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Events\CallInitiated;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallSignal;

class CallController extends Controller
{
    /**
     * Start a call (ring participants)
     */
    public function startCall(Request $request)
    {
        $request->validate([
            'participant_ids'   => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
            'type'              => 'required|in:voice,video',
            'conversation_id'   => 'required|exists:conversations,id',
        ]);

        $caller = Auth::user();

        $participants = User::whereIn('id', $request->participant_ids)
            ->where('availability', 'available')
            ->where('do_not_disturb', false)
            ->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'No available participants to call.',
            ], 403);
        }

        $callId = (string) Str::uuid();

        foreach ($participants as $participant) {
            broadcast(new CallInitiated(
                caller_id: $caller->id,
                caller_name: $caller->name,
                participant_id: $participant->id,
                type: $request->type,
                conversation_id: $request->conversation_id,
            ))->toOthers();
        }
$authToken = $this->generate100msToken($caller, $callId);

return response()->json([
    'message' => 'Call initiated',
    'call_id' => $callId,
    'caller' => $caller,
    'participants' => $participants,
    'type' => $request->type,
    'auth_token' => $authToken,
]);

    }


    private function generate100msToken($user, string $roomId): string
{
    $managementToken = env('HMS_MANAGEMENT_TOKEN'); // from 100ms dashboard
    $templateId = env('HMS_TEMPLATE_ID');           // room template id

    if (!$managementToken || !$templateId) {
        throw new \RuntimeException('Missing HMS_MANAGEMENT_TOKEN or HMS_TEMPLATE_ID');
    }

    $response = Http::withToken($managementToken)
        ->acceptJson()
        ->post('https://api.100ms.live/v2/room-codes/room-code', [
            'room_id' => $roomId, // or your actual 100ms room id mapping
            'role' => 'guest',    // match role configured in your template
            'user_id' => (string) $user->id,
        ]);

    if (!$response->successful()) {
        Log::error('100ms token generation failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \RuntimeException('Failed to generate 100ms auth token');
    }

    $json = $response->json();
    // adjust key if your endpoint returns a different structure
    return data_get($json, 'token')
        ?? data_get($json, 'room_code')
        ?? throw new \RuntimeException('100ms token missing in response');
}


    /**
     * Accept a call
     */
  public function acceptCall(Request $request)
{
    $request->validate([
        'call_id'   => 'required|string',
        'caller_id' => 'required|exists:users,id',
    ]);

    $user = Auth::user();

    // Generate 100ms token for the accepter (callee)
    $authToken = $this->generate100msToken($user, $request->call_id); // implement this

    broadcast(new CallAccepted(
        call_id: $request->call_id,
        accepter_id: $user->id,
        caller_id: $request->caller_id,
    ))->toOthers();

    return response()->json([
        'message' => 'Call accepted',
        'auth_token' => $authToken,
    ]);
}


    /**
     * WebRTC signaling (offer / answer / ICE)
     */
    public function signal(Request $request)
    {
        $request->validate([
            'conversation_id'  => 'required|exists:conversations,id',
            'to_user_id' => 'required|exists:users,id',
            'signal'           => 'required|array',
            'signal.type'      => 'required|in:offer,answer,ice-candidate',
            'signal.payload'   => 'required',
            'type'             => 'nullable|in:video,audio',
        ]);

        $signal = $request->signal;

        $fromUserId = $request->user()->id;
        $toUserId = $request->to_user_id; // if single participant, or you loop through participants
        $callId = $request->conversation_id;
        $signalType = $signal['type']; // 'offer', 'answer', 'ice-candidate'
        $data = $signal['payload']; // array containing SDP or ICE candidate
        $type = $signal['type'];   

        broadcast(new CallSignal(
            call_id: $callId,
            from_user_id: $fromUserId,
            to_user_id: $toUserId,
            signal_type: $signalType,
            data: $data,
            type: $type,
        ))->toOthers();

        return response()->json(['status' => 'sent']);
    }

    /**
     * End a call
     */
    public function endCall(Request $request)
    {
        $request->validate([
            'call_id'           => 'required|string',
            'participant_ids'   => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $caller = Auth::user();
        $participants = User::whereIn('id', $request->participant_ids)->get();

        foreach ($participants as $participant) {
            broadcast(new CallEnded(
                call_id: $request->call_id,
                caller_id: $caller->id,
                participant_id: $participant->id,
            ))->toOthers();
        }

        return response()->json([
            'message' => 'Call ended',
        ]);
    }
}