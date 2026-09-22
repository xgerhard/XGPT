<?php

namespace App\Chat\Providers;

use App\Chat\Contracts\BotProvider;
use App\Chat\Contracts\SupportsAutoInstall;
use App\Chat\IncomingMessage;
use App\Models\User;
use Illuminate\Http\Request;
use xgerhard\nbheaders\NBHeaders;

class NightbotBotProvider implements BotProvider, SupportsAutoInstall
{
    /**
     * The exact command URL fragment Nightbot's $(urlfetch ...) must contain for a channel's
     * installed command to be recognized as "the XGPT command" by install()/matches-in-dashboard.
     * Keep this in sync with routes/api.php — the public contract must not change.
     */
    public const COMMAND_URL = 'https://xgpt.gerhard.dev/api/command?q=$(querystring)';

    private ?NBHeaders $headers = null;

    public function key(): string
    {
        return 'nightbot';
    }

    public function matches(Request $request): bool
    {
        return $this->headers($request)->isNightbotRequest();
    }

    public function parse(Request $request): IncomingMessage
    {
        $headers = $this->headers($request);
        $user = $headers->getUser();
        $channel = $headers->getChannel();

        return new IncomingMessage(
            botProvider: $this->key(),
            provider: $channel->provider,
            providerId: $channel->providerId,
            channelDisplayName: $channel->displayName,
            username: $user->displayName ?? null,
            userProvider: $user->provider ?? null,
            userProviderId: $user->providerId ?? null,
            userLevel: $user->userLevel ?? null,
            message: (string) $request->query('q', ''),
            responseUrl: $this->safeResponseUrl($headers->getResponseUrl()),
        );
    }

    public function install(User $channel, string $accessToken, string $commandName = 'ai'): void
    {
        $api = (new NightbotApiClient)->setAccessToken($accessToken);
        $commands = $api->getCustomCommands();

        $ourUrl = self::COMMAND_URL.'&token='.$channel->token;
        $matched = false;

        foreach ($commands['commands'] ?? [] as $command) {
            if (str_contains($command['message'], self::COMMAND_URL)) {
                $matched = true;

                if ($command['message'] !== $ourUrl) {
                    $api->editCustomCommand($command['_id'], ['message' => $ourUrl]);
                }
            }
        }

        if (! $matched) {
            $api->addCustomCommand([
                'coolDown' => 5,
                'message' => $ourUrl,
                'name' => $commandName,
                'userLevel' => 'everyone',
            ]);
        }
    }

    public function commandSnippet(User $channel): string
    {
        return self::COMMAND_URL.'&token='.$channel->token;
    }

    /**
     * Builds NBHeaders from Laravel's own $request object rather than letting the package read
     * PHP's raw superglobals itself (its default constructor calls getallheaders()/$_SERVER
     * directly) — that path works for a real production request, but silently sees nothing in
     * tests (Laravel's HTTP test client never touches real superglobals) and ignores the $request
     * this method is handed. Feeding it explicitly through setUser()/setChannel()/setResponseUrl()
     * keeps the package's isNightbotRequest()/isUserModerator() logic while making this testable.
     */
    private function headers(Request $request): NBHeaders
    {
        if ($this->headers !== null) {
            return $this->headers;
        }

        $headers = new NBHeaders;

        // Lets /api/command be exercised from a browser during local development — only ever
        // active when APP_DEBUG is on, and only when the request doesn't already carry real
        // Nightbot headers.
        if (config('app.debug') && ! $request->headers->has('Nightbot-Channel')) {
            $headers->setUser([
                'name' => 'xgerhard',
                'displayName' => 'xgerhard',
                'provider' => 'twitch',
                'providerId' => '49056910',
                'userLevel' => 'owner',
            ]);
            $headers->setChannel([
                'name' => 'xgerhard',
                'displayName' => 'xgerhard',
                'provider' => 'twitch',
                'providerId' => '49056910',
            ]);

            return $this->headers = $headers;
        }

        if ($user = $this->parseHeaderValue($request, 'Nightbot-User')) {
            $headers->setUser($user);
        }

        if ($channel = $this->parseHeaderValue($request, 'Nightbot-Channel')) {
            $headers->setChannel($channel);
        }

        if ($responseUrl = $request->header('Nightbot-Response-Url')) {
            $headers->setResponseUrl($responseUrl);
        }

        return $this->headers = $headers;
    }

    /**
     * Nightbot-User / Nightbot-Channel are query-string-encoded (e.g. "name=x&displayName=x&
     * provider=twitch&providerId=1"), matching nbheaders' own parsing.
     */
    private function parseHeaderValue(Request $request, string $header): ?array
    {
        $value = $request->header($header);

        if (! $value) {
            return null;
        }

        parse_str($value, $parsed);

        return $parsed;
    }

    /**
     * `isNightbotRequest()` has no cryptographic verification (nbheaders' source: it's just
     * `!! $channel`, i.e. "was a Nightbot-Channel header present at all"), so
     * Nightbot-Response-Url is attacker-controlled on a spoofed request. Only Nightbot's own API
     * host is ever forwarded to, to avoid an open SSRF relay.
     */
    private function safeResponseUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === 'api.nightbot.tv' ? $url : null;
    }
}
