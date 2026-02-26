<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuggestionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $suggestion = Suggestion::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? true),
            'status' => 'new',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Suggestion submitted successfully',
            'data' => $suggestion,
        ], 201);
    }

    public function mine(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $suggestions = Suggestion::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Your suggestions fetched successfully',
            'data' => $suggestions,
        ]);
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $perPage = (int) $request->get('per_page', 15);
        $status = $request->get('status');

        $suggestions = Suggestion::query()
            ->with('user:id,name,email')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);

        $suggestions->getCollection()->transform(function (Suggestion $suggestion) {
            if ($suggestion->is_anonymous) {
                $suggestion->setRelation('user', null);
            }

            return $suggestion;
        });

        return response()->json([
            'status' => true,
            'message' => 'Suggestions fetched successfully',
            'data' => $suggestions,
        ]);
    }

    public function updateStatus(Request $request, Suggestion $suggestion)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'status' => 'required|in:new,reviewed,resolved',
        ]);

        $suggestion->update([
            'status' => $validated['status'],
        ]);

        $suggestion->load('user:id,name,email');
        if ($suggestion->is_anonymous) {
            $suggestion->setRelation('user', null);
        }

        return response()->json([
            'status' => true,
            'message' => 'Suggestion status updated successfully',
            'data' => $suggestion,
        ]);
    }

    protected function ensureAdmin(): void
    {
        $user = Auth::user();

        
    if (!in_array((string) $user->role, ['hr', 'admin', 'super_admin'], true)) {
        abort(403, 'Only HR/Admin users can perform this action');
    }
    }
}
