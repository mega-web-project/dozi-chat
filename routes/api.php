<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\QrAuthController;

Route::prefix('v1')->group(function () {
Broadcast::routes(['middleware' => ['auth:sanctum']]);

//   Broadcast::routes([
//         'middleware' => ['auth:sanctum'],
//         'prefix' => 'broadcasting', 
//     ]);
    // =============================
    // Public routes (no auth)
    // =============================
    // Route::post('/register', [AuthController::class, 'register']);

   

     Route::post('/gr/generate', [QrAuthController::class, 'generateQr']); // no auth
     Route::post('/qr/verify', [QrAuthController::class, 'verifyOtp']); // web login
    Route::post('/request-activation', [AuthController::class, 'requestActivation']); // user requests OTP
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/set-password', [AuthController::class, 'setPassword']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);

    // =============================
    // Protected routes (requires auth:sanctum)
    // =============================
    Route::middleware('auth:sanctum')->group(function () {
         Route::post('/scan', [QrAuthController::class, 'scanQr']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
     
    });


});

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::post('/users', [AuthController::class, 'registerByAdmin']); // admin creates a user
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/user/avatar', [UserController::class, 'uploadAvatar']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
   
    // --------------------------
    // Conversations
    // --------------------------
    Route::get("/users",  [ConversationController::class, 'AllUsers']);
    Route::get('/contacts', [ConversationController::class, 'contacts']); // Get contacts
    Route::get('/conversations', [ConversationController::class, 'index']); // List user conversations
    Route::post('/conversations', [ConversationController::class, 'store']); // Create new conversation

    // Add participant to conversation
    Route::post('/conversations/private', [ConversationController::class, 'privateChat']);
    Route::post('/conversations/{conversation}/participants', [ConversationController::class, 'addParticipant']);

    // Remove participant from conversation
    Route::delete('/conversations/{conversation}/participants/{user_id}', [ConversationController::class, 'removeParticipant']);


    // --------------------------
    // Messages
    // --------------------------
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']); // List messages
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'send']); // Send message
    Route::post('/messages/{message}/read', [MessageController::class, 'markRead']); // Mark read
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing']); // Typing indicator

    Route::prefix('call')->group(function () {
    Route::post('/start', [CallController::class, 'startCall']);
      Route::post('/accept', [CallController::class, 'acceptCall']);
    Route::post('/end', [CallController::class, 'endCall']);

    // routes/api.php (inside v1 group)
    Route::post('/signal', [CallController::class, 'signal']);

    });


    // News
    Route::get('/news', [NewsController::class, 'index']);
    Route::post('/news', [NewsController::class, 'store']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    Route::post('/news/{id}/comments', [NewsController::class, 'addComment']);
    Route::post('/news/{id}/poll/vote', [NewsController::class, 'vote']);

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
