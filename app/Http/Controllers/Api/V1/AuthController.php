<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\UserOnlineStatus;
use Illuminate\Support\Facades\Hash;
use App\Mail\OTPMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user and send OTP
     */
public function registerByAdmin(Request $request)
{
    $request->validate([
        'name'       => 'required|string|max:255',
        'email'      => 'required|email|unique:users,email',
        'role'       => 'required|in:user,admin',
        'status'     => 'nullable|in:active,inactive',
        'department' => 'nullable|string|max:255',
        'job_title'  => 'nullable|string|max:255',
        'position'   => 'nullable|string|max:255',
        'location'   => 'nullable|string|max:255',
    ]);

    // Create user WITHOUT password
    $user = User::create([
        'name'              => $request->name,
        'email'             => $request->email,
        'role'              => $request->role,
        'status'            => $request->status ?? 'inactive',
        'department'        => $request->department,
        'job_title'         => $request->job_title,
        'position'          => $request->position,
        'location'          => $request->location,
        'password'          => null, // password to be set by user
        'email_verified_at' => null,
    ]);

    // Send notification email about account creation
    Mail::to($user->email)->send(new \App\Mail\UserCreatedNotification($user));

    return response()->json([
        'message' => 'User created successfully. A notification email has been sent to the user.',
        'user' => $user,
    ], 201);
}



public function requestActivation(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $user = User::where('email', $request->email)->first();

    if ($user->email_verified_at) {
        return response()->json([
            'message' => 'Email already verified, you can login.',
        ]);
    }

    // Generate OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $user->update([
        'email_otp' => $otp,
        'email_otp_sent_at' => now(),
    ]);

    // Send OTP email
    Mail::to($user->email)->send(new OTPMail($user->name, $otp));

    return response()->json([
        'message' => 'OTP sent. Please check your email.',
    ]);
}


    /**
     * Verify email using OTP
     */
 public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'otp'   => 'required|digits:6',
    ]);

    $otp = str_pad($request->otp, 6, '0', STR_PAD_LEFT);

    $user = User::where('email', $request->email)
                ->where('email_otp', $otp)
                ->first();

    if (!$user) {
        throw ValidationException::withMessages([
            'otp' => ['Invalid OTP'],
        ]);
    }

    // OTP expires in 10 minutes
    if ($user->email_otp_sent_at && $user->email_otp_sent_at->diffInMinutes(now()) > 10) {
        throw ValidationException::withMessages([
            'otp' => ['OTP has expired. Request a new one.'],
        ]);
    }

    $user->update([
        'email_verified_at' => now(),
        'email_otp' => null,
        'email_otp_sent_at' => null,
        'status' => 'active', // activate account
    ]);

    return response()->json([
        'message' => 'Email verified successfully. You can now login.',
    ]);
}


    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found'],
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified',
            ]);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_otp'         => $otp,
            'email_otp_sent_at' => now(),
        ]);

        // Send OTP email
        Mail::to($user->email)->send(new OTPMail($user->name, $otp));

        return response()->json([
            'message' => 'OTP resent successfully',
        ]);
    }

    // set password function can be added here if needed
    public function setPassword(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !$user->email_verified_at) {
        throw ValidationException::withMessages([
            'email' => ['You must verify your email first.'],
        ]);
    }

    if ($user->password) {
        throw ValidationException::withMessages([
            'password' => ['Password is already set.'],
        ]);
    }

    $user->update([
        'password' => Hash::make($request->password),
        'status'   => 'active', // activate account after setting password
    ]);

    return response()->json([
        'message' => 'Password set successfully. You can now login.',
    ]);
}

    /**
     * Login user (only verified emails)
     */
public function login(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $validated['email'])->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid credentials'],
        ]);
    }

    if (!$user->email_verified_at) {
        throw ValidationException::withMessages([
            'email' => ['Please verify your email first.'],
        ]);
    }

    if ($user->status !== 'active') {
        throw ValidationException::withMessages([
            'email' => ['Your account is inactive. Contact admin.'],
        ]);
    }

    // Update last seen
    $user->update(['last_seen_at' => now()]);

    // Determine online presence respecting Do Not Disturb
    $isOnline = !$user->do_not_disturb && $user->availability === 'available';

    // Broadcast presence
    broadcast(new UserOnlineStatus($user->id, $isOnline));

    $token = $user->createToken('dozi-chat')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'last_seen_at' => $user->last_seen_at,
            'role' => $user->role,
            'status' => $user->status,
            'availability' => $user->availability,
            'do_not_disturb' => $user->do_not_disturb,
        ],
        'token' => $token,
    ]);
}



    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $user = $request->user(); // Get the authenticated user BEFORE deleting token

        // Revoke current access token
        $request->user()->currentAccessToken()->delete();

        // Update last seen
        $user->update(['last_seen_at' => now()]);

        // Broadcast offline status
        broadcast(new UserOnlineStatus($user->id, false))->toOthers();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

}
