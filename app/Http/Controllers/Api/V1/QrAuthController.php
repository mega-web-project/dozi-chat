<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class QrAuthController extends Controller
{
    /**
     * 1️⃣ Web: generate QR (no auth)
     */
    public function generateQr()
    {
        $token = Str::uuid();

        // Store temporary QR token in cache (not tied to user yet)
        \Cache::put(
            'qr_login_' . $token,
            [
                'expires_at' => now()->addMinutes(2),
                'user_id' => null
            ],
            now()->addMinutes(2)
        );

        $qr = QrCode::size(300)->generate(json_encode([
            'type' => 'qr_login',
            'token' => $token,
        ]));

        return response()->json([
            'qr' => $qr,          // SVG
            'token' => $token,    // temporary token for web polling
            'expires_in' => 120,
        ]);
    }

    /**
     * 2️⃣ Mobile: scan QR (auth required)
     */
    public function scanQr(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $cacheKey = 'qr_login_' . $request->token;

        $qrData = \Cache::get($cacheKey);

        if (!$qrData || now()->gt($qrData['expires_at'])) {
            return response()->json([
                'message' => 'QR expired or invalid',
            ], 401);
        }

        $otp = random_int(100000, 999999);

        $user = Auth::user(); // Mobile user must be logged in

        // Save OTP & link QR token to user
        $user->update([
            'qr_token' => $request->token,
            'qr_otp' => $otp,
            'qr_expires_at' => now()->addMinute(),
        ]);

        // Update cache to tie user ID to token
        \Cache::put($cacheKey, [
            'expires_at' => $qrData['expires_at'],
            'user_id' => $user->id
        ], now()->addMinutes(2));

        return response()->json([
            'otp' => $otp
        ]);
    }

    /**
     * 3️⃣ Web: verify OTP (no auth)
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'otp' => 'required|digits:6',
        ]);

        $cacheKey = 'qr_login_' . $request->token;

        $qrData = \Cache::get($cacheKey);

        if (!$qrData || !$qrData['user_id']) {
            return response()->json([
                'message' => 'Invalid or expired QR session',
            ], 401);
        }

        $user = User::where('id', $qrData['user_id'])
            ->where('qr_otp', $request->otp)
            ->where('qr_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 401);
        }

        // Cleanup QR data
        $user->update([
            'qr_token' => null,
            'qr_otp' => null,
            'qr_expires_at' => null,
        ]);
        \Cache::forget($cacheKey);

        // Issue login token for web
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }
}
