<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Events\CallInitiated;
use App\Events\CallEnded;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class CallController extends Controller
{
    //
       public function startCall(Request $request)
    {
        $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
            'type' => 'required|in:voice,video',
        ]);

        $caller = Auth::user();
        $participants = User::whereIn('id', $request->participant_ids)
                            ->where(function($q) {
                                $q->where('availability', 'available')
                                  ->where('do_not_disturb', false);
                            })->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'No available participants to call.',
            ], 403);
        }

        // Broadcast call initiation to each participant
        foreach ($participants as $participant) {
            broadcast(new CallInitiated(
                caller_id: $caller->id,
                caller_name: $caller->name,
                participant_id: $participant->id,
                type: $request->type
            ))->toOthers();
        }

        return response()->json([
            'message' => 'Call initiated successfully',
            'caller' => $caller,
            'participants' => $participants,
            'type' => $request->type,
        ]);
    }

    /**
     * End a call (1-to-1 or group)
     */
    public function endCall(Request $request)
    {
        $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $caller = Auth::user();
        $participants = User::whereIn('id', $request->participant_ids)->get();

        foreach ($participants as $participant) {
            broadcast(new CallEnded(
                caller_id: $caller->id,
                participant_id: $participant->id
            ))->toOthers();
        }

        return response()->json([
            'message' => 'Call ended successfully',
            'participants' => $participants,
        ]);
    }
}
