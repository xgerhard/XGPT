<?php

namespace App\Chat\Providers;

use App\Chat\Contracts\BotProvider;
use App\Chat\Contracts\SupportsAutoInstall;
use App\Chat\IncomingMessage;
use App\Exceptions\CommandNameTakenException;
use App\Models\User;
use Illuminate\Http\Request;

class NightbotBotProvider implements BotProvider, SupportsAutoInstall
{
    /**
     * The exact command URL fragment Nightbot's $(urlfetch ...) must contain for a channel's
     * installed command to be recognized as "the XGPT command" by install()/matches-in-dashboard.
     * Keep this in sync with routes/api.php — the public contract must not change.
     */
    public const COMMAND_URL = 'https://xgpt.gerhard.dev/api/command?q=$(querystring)';

    public const DEFAULT_COMMAND_NAME = '!ai';

    private const CHANNEL_FIELDS = ['name', 'displayName', 'provider', 'providerId'];

    private const USER_FIELDS = ['name', 'displayName', 'provider', 'providerId', 'userLevel'];

    /**
     * A length instruction some channels still have appended to the query in their Nightbot
     * command: "answer in 150 characters or less" (including the "150% 20characters" form a
     * doubled "%%20" in the command decodes to) or "keep all replies under 150 characters".
     * Length is handled by ChatResponder's framing.
     */
    private const LENGTH_INSTRUCTION = '/\s*(?:answer\s+in\s+\d+\s*%?\s*(?:20)?char(?:acter)?s?\s+or\s+(?:less?|fewer)'
        .'|keep\s+(?:all\s+)?(?:replies|answers|responses)\s+under\s+\d+\s*char(?:acter)?s?)\s*[.!]?\s*$/i';

    private ?array $headers = null;

    public function key(): string
    {
        return 'nightbot';
    }

    public function matches(Request $request): bool
    {
        ['user' => $user, 'channel' => $channel] = $this->headers($request);

        // Only a shape check: the headers are still spoofable. Nightbot-User is absent on timer
        // messages, but when it's sent it must be complete too.
        return $this->hasFields($channel, self::CHANNEL_FIELDS)
            && ($user === null || $this->hasFields($user, self::USER_FIELDS));
    }

    public function parse(Request $request): IncomingMessage
    {
        ['user' => $user, 'channel' => $channel, 'responseUrl' => $responseUrl] = $this->headers($request);

        return new IncomingMessage(
            botProvider: $this->key(),
            provider: $channel['provider'],
            providerId: $channel['providerId'],
            channelDisplayName: $channel['displayName'],
            username: $user['displayName'] ?? null,
            userProvider: $user['provider'] ?? null,
            userProviderId: $user['providerId'] ?? null,
            userLevel: $user['userLevel'] ?? null,
            message: trim(preg_replace(self::LENGTH_INSTRUCTION, '', (string) $request->query('q', ''))),
            responseUrl: $this->safeResponseUrl($responseUrl),
        );
    }

    /**
     * Adds the command under $commandName, or brings every existing XGPT command up to date
     * (the name is then ignored). Never overwrites an unrelated command that has the same name.
     *
     * @throws CommandNameTakenException
     */
    public function install(User $channel, string $accessToken, string $commandName): void
    {
        $api = (new NightbotApiClient)->setAccessToken($accessToken);
        $commands = $api->getCustomCommands();

        $snippet = $this->commandSnippet($channel);
        $matched = false;

        foreach ($commands['commands'] ?? [] as $command) {
            if (str_contains($command['message'], self::COMMAND_URL)) {
                $matched = true;

                if ($command['message'] !== $snippet) {
                    $api->editCustomCommand($command['_id'], ['message' => $snippet]);
                }
            }
        }

        if (! $matched) {
            $taken = collect($commands['commands'] ?? [])
                ->contains(fn (array $command) => strcasecmp($command['name'], $commandName) === 0);

            if ($taken) {
                throw new CommandNameTakenException;
            }

            $api->addCustomCommand([
                'coolDown' => 5,
                'message' => $snippet,
                'name' => $commandName,
                'userLevel' => 'everyone',
            ]);
        }
    }

    /**
     * The full command message: Nightbot only fetches the URL and posts the response when it's
     * wrapped in $(urlfetch ...); a bare URL would be posted into chat as-is.
     */
    public function commandSnippet(User $channel): string
    {
        return '$(urlfetch '.self::COMMAND_URL.'&token='.$channel->token.')';
    }

    /**
     * The parsed Nightbot-User / Nightbot-Channel / Nightbot-Response-Url headers, read from
     * Laravel's $request (never PHP's superglobals, which the HTTP test client doesn't populate).
     *
     * @return array{user: ?array, channel: ?array, responseUrl: ?string}
     */
    private function headers(Request $request): array
    {
        if ($this->headers !== null) {
            return $this->headers;
        }

        // Lets /api/command be exercised from a browser during local development — only ever
        // active when APP_DEBUG is on, and only when the request doesn't already carry real
        // Nightbot headers.
        if (config('app.debug') && ! $request->headers->has('Nightbot-Channel')) {
            return $this->headers = [
                'user' => [
                    'name' => 'xgerhard',
                    'displayName' => 'xgerhard',
                    'provider' => 'twitch',
                    'providerId' => '49056910',
                    'userLevel' => 'owner',
                ],
                'channel' => [
                    'name' => 'xgerhard',
                    'displayName' => 'xgerhard',
                    'provider' => 'twitch',
                    'providerId' => '49056910',
                ],
                'responseUrl' => null,
            ];
        }

        return $this->headers = [
            'user' => $this->parseHeaderValue($request, 'Nightbot-User'),
            'channel' => $this->parseHeaderValue($request, 'Nightbot-Channel'),
            'responseUrl' => $request->header('Nightbot-Response-Url') ?: null,
        ];
    }

    private function hasFields(?array $header, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! filled($header[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nightbot-User / Nightbot-Channel are query-string-encoded (e.g. "name=x&displayName=x&
     * provider=twitch&providerId=1").
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
     * Nightbot's headers carry no cryptographic verification — matches() only checks that the
     * headers are well-formed — so Nightbot-Response-Url is attacker-controlled on a
     * spoofed request. Only Nightbot's own API
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
