<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Conversation extends Model
{
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable  = ['id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Starts a conversation with a code that isn't in use
     */
    public static function start(): self
    {
        do {
            $id = generate_random_string(
                3, // length
                true, // letters
                false // numbers
            );
        } while (self::find($id));

        return self::create(['id' => $id]);
    }

    /**
     * The last conversation of the viewer, if it's not expired
     */
    public static function lastOf(string $viewer): ?self
    {
        return self::find(Cache::store('conversations')->get($viewer));
    }

    public function rememberFor(string $viewer): void
    {
        Cache::store('conversations')->put($viewer, $this->id, now()->addHours(2));
    }

    public function addMessage(string $role, string $content): void
    {
        $this->messages()->create(['role' => $role, 'content' => $content]);
    }
}
