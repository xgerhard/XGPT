<?php

namespace App\Chat;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Finds-or-creates the channel (User row) behind an incoming chat message or a dashboard login.
 * Both paths funnel through here so a channel using the free tier (never having visited the
 * dashboard) still gets a real row to hang rolling history and rate-limit state off of — the
 * pre-rewrite app only persisted a User at login and used an in-memory stand-in otherwise, which
 * doesn't work now that history needs somewhere to live.
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
            $channel->settings()->create([
                'instructions' => $this->defaultInstructions($provider),
            ]);
            $channel->load('settings');
        } elseif ($channel->display_name !== $displayName) {
            // Keep display name fresh — streamers do rename.
            $channel->update(['display_name' => $displayName]);
        }

        return $channel;
    }

    public function defaultInstructions(string $provider): string
    {
        // YouTube chat messages have a much tighter length cap than Twitch/Discord.
        $length = $provider === 'youtube' ? 150 : 250;

        return sprintf(
            'You are a helpful assistant for a %s chat. Answer in %d characters or less.',
            ucfirst($provider),
            $length,
        );
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(8);
        } while (User::where('token', $token)->exists());

        return $token;
    }
}
