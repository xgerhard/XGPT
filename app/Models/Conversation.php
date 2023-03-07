<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable  = ['id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
