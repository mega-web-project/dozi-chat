<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Upload avatar (you already have this)
    public function uploadAvatar(Request $request)
{
    $user = Auth::user();

    $request->validate([
        'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    // Delete old avatar from S3
    if ($user->avatar) {
        Storage::disk('s3')->delete($user->avatar);
    }

    // Store new avatar in S3
    $path = $request->file('avatar')->store('avatars', 's3');

    // Save only path in database
    $user->update([
        'avatar' => $path
    ]);

    return response()->json([
        'message' => 'Profile image uploaded successfully',
        'avatar_url' => Storage::disk('s3')->url($path),
    ]);
}


    // Update user profile
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

       $validated = $request->validate([
        'name'           => 'sometimes|string|max:255',
        'email'          => ['sometimes','email', Rule::unique('users')->ignore($user->id)],
        'department'     => 'sometimes|string|max:255',
        'job_title'      => 'sometimes|string|max:255',
        'position'       => 'sometimes|string|max:255',
        'location'       => 'sometimes|string|max:255',
        'availability'   => 'sometimes|in:available,busy,offline',
        'do_not_disturb' => 'sometimes|boolean',
        'password'       => 'sometimes|string|min:8|confirmed',
        ]);


        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

}
