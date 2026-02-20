<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $roomId = $this->create100msRoom((int) $request->conversation_id);
        $callId = $roomId;

        foreach ($participants as $participant) {
            broadcast(new CallInitiated(
                caller_id: $caller->id,
                caller_name: $caller->name,
                participant_id: $participant->id,
                type: $request->type,
                conversation_id: $request->conversation_id,
            ))->toOthers();
        }
        $authToken = $this->generate100msToken($caller, $roomId);

return response()->json([
    'message' => 'Call initiated',
    'call_id' => $callId,
    'caller' => $caller,
    'participants' => $participants,
    'type' => $request->type,
    'auth_token' => $authToken,
]);

    }


    private function create100msRoom(int $conversationId): string
    {
        $managementToken = env('HMS_MANAGEMENT_TOKEN');
        $templateId = env('HMS_TEMPLATE_ID');

        if (!$managementToken || !$templateId) {
            throw new \RuntimeException('Missing HMS_MANAGEMENT_TOKEN or HMS_TEMPLATE_ID');
        }

        $response = Http::withToken($managementToken)
            ->acceptJson()
            ->post('https://api.100ms.live/v2/rooms', [
                'name' => 'conversation-' . $conversationId . '-' . Str::lower(Str::random(8)),
                'description' => 'Call room for conversation ' . $conversationId,
                'template_id' => $templateId,
            ]);

        if (!$response->successful()) {
            Log::error('100ms room creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create 100ms room');
        }

        $roomId = data_get($response->json(), 'id');

        if (!$roomId) {
            throw new \RuntimeException('100ms room id missing in response');
        }

        return (string) $roomId;
    }

    private function generate100msToken($user, string $roomId): string
    {
        $accessKey = env('HMS_ACCESS_KEY');
        $secret = env('HMS_SECRET');
        $role = env('HMS_ROLE', 'guest');

        if (!$accessKey || !$secret) {
            throw new \RuntimeException('Missing HMS_ACCESS_KEY or HMS_SECRET');
        }

        $now = time();
        $payload = [
            'access_key' => $accessKey,
            'room_id' => $roomId,
            'user_id' => (string) $user->id,
            'role' => $role,
            'type' => 'app',
            'version' => 2,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 24 * 60 * 60,
            'jti' => (string) Str::uuid(),
        ];

        return $this->encodeHs256Jwt($payload, $secret);
    }

    private function encodeHs256Jwt(array $payload, string $secret): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
