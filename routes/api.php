<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\CallController;

Route::prefix('v1')->group(function () {

    // =============================
    // Public routes (no auth)
    // =============================
    // Route::post('/register', [AuthController::class, 'register']);
    Route::post('/request-activation', [AuthController::class, 'requestActivation']); // user requests OTP
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/set-password', [AuthController::class, 'setPassword']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);

    // =============================
    // Protected routes (requires auth:sanctum)
    // =============================
    Route::middleware('auth:sanctum')->group(function () {
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
    Route::post('/end', [CallController::class, 'endCall']);
    });

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
