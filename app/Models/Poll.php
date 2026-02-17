<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'news_id',
        'question',
    ];

    public function news()
    {
        return $this->belongsTo(News::class);
    }

    public function options()
    {
        return $this->hasMany(PollOption::class);
    }
}
