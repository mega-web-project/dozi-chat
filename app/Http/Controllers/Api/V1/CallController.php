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

        return response()->json([
            'message' => 'Call initiated',
            'call_id' => $callId,
            'caller' => $caller,
            'participants' => $participants,
            'type' => $request->type,
        ]);
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

        broadcast(new CallAccepted(
            call_id: $request->call_id,
            accepter_id: Auth::id(),
            caller_id: $request->caller_id,
        ))->toOthers();

        return response()->json([
            'message' => 'Call accepted',
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