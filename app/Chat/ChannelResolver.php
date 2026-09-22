<?php

namespace App\Chat;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Finds-or-creates the channel (User row) behind an incoming chat message or a dashboard login.
 * Both paths funnel through here so a free-tier channel (one that's never visited the dashboard)
 * still gets a real row to hang rolling history and rate-limit state off of.
 */
class ChannelResolver
{
    public function resolve(string $provider, string $providerId, string $displayName): User
    {
        $channel = User::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $providerId],
            ['display_name' => $displayName, 'token' => $this->generateUniqueToken()],
        );

        if ($channel->wasRecentlyCreated) {
            // No default `instructions` to seed here — the multi-user/length-limit framing is
            // mandatory and always injected by ChatResponder::instructions(), not stored per
            // channel. This field is purely optional flavor text a streamer can add on top.
            $channel->settings()->create();
            $channel->load('settings');
        } elseif ($channel->display_name !== $displayName) {
            // Keep display name fresh — streamers do rename.
            $channel->update(['display_name' => $displayName]);
        }

        return $channel;
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(8);
        } while (User::where('token', $token)->exists());

        return $token;
    }
}
