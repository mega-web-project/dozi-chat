<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'file_url',
        'file_type',
        'file_size',
        'duration',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
