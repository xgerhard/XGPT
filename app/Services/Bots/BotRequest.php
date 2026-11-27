<?php

namespace App\Services\Bots;

/**
 * A verified command from a chat bot: the channel, and the viewer who used it (none on timers)
 */
final readonly class BotRequest
{
    public function __construct(
        public string $channelProvider,
        public string $channelId,
        public ?string $userName = null,
        public ?string $userProvider = null,
        public ?string $userId = null,
        // Only Nightbot can send a second message
        public ?string $responseUrl = null,
    ) {
    }

    public function hasUser(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Where the reply is shown, decides the length of a chat message
     */
    public function platform(): string
    {
        return $this->userProvider ?? $this->channelProvider;
    }

    /**
     * Identifies the viewer within the channel, null on timers
     */
    public function viewerKey(): ?string
    {
        return $this->hasUser()
            ? implode(':', [$this->channelProvider, $this->channelId, $this->userProvider, $this->userId])
            : null;
    }
}
