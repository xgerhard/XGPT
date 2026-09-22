<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A channel: the streamer identity behind a (provider, provider_id) pair. Created lazily on
 * first bot request or first dashboard login (see App\Chat\ChannelResolver).
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $with = ['settings'];

    protected $fillable = [
        'display_name',
        'provider',
        'provider_id',
        'token',
        'sponsor',
    ];

    protected function casts(): array
    {
        return [
            'sponsor' => 'boolean',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'channel_id');
    }
}
