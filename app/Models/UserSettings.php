<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class UserSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructions',
        'ai_provider',
        'ai_api_key',
        'mention_user',
        'show_sponsor_heart',
    ];

    // ai_api_key is encrypted, but it's still ciphertext of a live secret — never let it leak
    // through an accidental toArray()/toJson()/debug dump of the model.
    protected $hidden = ['ai_api_key'];

    protected function casts(): array
    {
        return [
            'mention_user' => 'boolean',
            'show_sponsor_heart' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Decrypt the stored API key. Returns null if there isn't one, or if it fails to decrypt
     * (e.g. APP_KEY rotated without re-encrypting) rather than blowing up the request.
     */
    public function getDecryptedAiApiKeyAttribute(): ?string
    {
        if (! $this->attributes['ai_api_key']) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['ai_api_key']);
        } catch (DecryptException $e) {
            Log::warning('Failed to decrypt ai_api_key.', ['user_id' => $this->user_id]);

            return null;
        }
    }

    public function setAiApiKeyAttribute(?string $value): void
    {
        $this->attributes['ai_api_key'] = $value ? Crypt::encryptString($value) : null;
    }
}
