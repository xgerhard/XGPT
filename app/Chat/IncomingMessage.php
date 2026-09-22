<?php

namespace App\Chat;

/**
 * Normalized shape of a chat-command request, regardless of which bot relayed it. Bot-specific
 * request parsing (headers, tokens, quirks) stays inside each BotProvider — everything downstream
 * of App\Chat\BotProviderRegistry only ever sees this.
 */
final class IncomingMessage
{
    public function __construct(
        public readonly string $botProvider,   // e.g. 'nightbot' — which BotProvider parsed this
        public readonly string $provider,      // underlying streaming platform, e.g. 'twitch'
        public readonly string $providerId,    // channel's platform-specific id
        public readonly string $channelDisplayName,
        public readonly ?string $username,     // chatter's display name; null for timer/no-user requests
        public readonly ?string $userProvider,      // chatter's own platform (for badge lookups)
        public readonly ?string $userProviderId,
        public readonly ?string $userLevel,    // e.g. 'owner'/'moderator'/'everyone', bot-specific vocabulary
        public readonly string $message,
        // Where to send an overflow second message, if this bot supports it — already
        // host-validated by the BotProvider that produced this DTO. Never trust this blindly
        // downstream; each BotProvider is responsible for only ever handing back a safe URL.
        public readonly ?string $responseUrl,
    ) {}

    public function isModeratorOrOwner(): bool
    {
        return in_array($this->userLevel, ['owner', 'moderator'], true);
    }
}
