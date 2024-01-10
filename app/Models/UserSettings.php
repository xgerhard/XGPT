<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class UserSettings extends Model
{
    protected $fillable = [
        'api_key',
        'start_instructions',
        'end_instructions',
        'show_conversation_id',
        'mention_user',
        'show_sponsor_heart'
    ];

    public function getApiKeyAttribute()
    {
        try {
            return Crypt::decryptString($this->attributes['api_key']);
        } catch (DecryptException $e) {
            // maybe log this
        }
    }
}
