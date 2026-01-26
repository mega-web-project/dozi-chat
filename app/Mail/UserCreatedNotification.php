<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Dozi Chat Account Has Been Created')
                    ->markdown('emails.user_created')
                    ->with([
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                    ]);
    }
}
