<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{
    protected $fillable = [
        'start_instructions',
        'end_instructions',
        'show_conversation_id',
        'mention_user',
        'show_sponsor_heart'
    ];
}
