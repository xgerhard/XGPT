<?php

namespace App\Chat\Contracts;

use App\Models\User;

/**
 * Implemented by BotProviders whose bot exposes an account-linked API for installing/updating
 * the custom command on the streamer's behalf (Nightbot today). A bot with no such API (e.g.
 * Fossabot, which has no OAuth/command-management API at all) implements BotProvider only, and
 * the dashboard falls back to showing a copy-paste command snippet instead of an install button.
 */
interface SupportsAutoInstall
{
    /**
     * Install or update the command in the channel's bot account using a previously-obtained
     * access token (never persisted — see the dashboard login flow).
     */
    public function install(User $channel, string $accessToken): void;

    /**
     * The command text a streamer would paste in manually, for display/troubleshooting even when
     * auto-install is available.
     */
    public function commandSnippet(User $channel): string;
}
