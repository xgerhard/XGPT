<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'channel_id',
        'role',
        'username',
        'content',
    ];

    // channel_id, not user_id: this points at the channel the message was said in (see
    // App\Chat\ChannelResolver), not at a "who sent it" identity — chatters aren't User rows,
    // just a plain `username` string on the message. Don't confuse this with a message author.
    public function channel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'channel_id');
    }
}
