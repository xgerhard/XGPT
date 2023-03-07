<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    protected $fillable  = ['role', 'content', 'conversation_id'];
}
