<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens; // <-- for API tokens

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Mass assignable
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'last_seen_at',
        'email_otp',          // OTP column
        'email_otp_sent_at',  // OTP sent timestamp
        'email_verified_at', // <--- ADD THIS
        'department',
        'job_title',
        'position',
        'location',
        'availability',
        'do_not_disturb',
        'role',      // new
       'status',    // new
    ];

    // Hidden fields
    protected $hidden = [
        'password',
        'remember_token',
        'email_otp',          // hide OTP from API
        'email_otp_sent_at',
    ];

    // Casts
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at'      => 'datetime',
        'email_otp_sent_at' => 'datetime',
    ];

    // Conversations the user participates in
    public function conversations()
    {
        return $this->belongsToMany(
            Conversation::class,
            'conversation_participants'
        )->withPivot(['role', 'joined_at', 'left_at']);
    }

    public function conversationParticipants()
{
    return $this->hasMany(ConversationParticipant::class);
}

    // Messages sent by user
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
