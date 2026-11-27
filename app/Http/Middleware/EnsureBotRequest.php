<?php

namespace App\Http\Middleware;

use App\Services\Bots\BotRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use xgerhard\nbheaders\NBHeaders;

/**
 * Only lets verified bot requests through, and makes them available as BotRequest
 */
class EnsureBotRequest
{
    public function __construct(private NBHeaders $nbheaders)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // With debug on, requests aren't verified and a test user is used without headers, so the command works from the browser
        $verify = ! config('app.debug');

        $bot = $this->nightbot($request, $verify)
            ?? $this->fossabot($request, $verify)
            ?? ($verify ? null : $this->testUser());

        if (! $bot) {
            return response('Only verified bot requests allowed.')->header('Content-Type', 'text/plain');
        }

        app()->instance(BotRequest::class, $bot);

        return $next($request);
    }

    private function nightbot(Request $request, bool $verify): ?BotRequest
    {
        if (! $this->nbheaders->hasNightbotHeaders() || ($verify && ! $this->isNightbot($request))) {
            return null;
        }

        $channel = $this->nbheaders->getChannel();
        $user = $this->nbheaders->getUser();

        return new BotRequest(
            $channel->provider,
            $channel->providerId,
            $user?->displayName,
            $user?->provider,
            $user?->providerId,
            $this->nbheaders->getResponseUrl()
        );
    }

    /**
     * Fossabot sends a token that is valid for one check, its headers say who is asking
     */
    private function fossabot(Request $request, bool $verify): ?BotRequest
    {
        $token = $request->header('x-fossabot-customapitoken');
        $channelProvider = $request->header('x-fossabot-channelprovider');
        $channelId = $request->header('x-fossabot-channelproviderid');

        if (! $token || ! $channelProvider || ! $channelId || ($verify && ! $this->isValidFossabotToken($token))) {
            return null;
        }

        $userId = $request->header('x-fossabot-message-userproviderid');

        return new BotRequest(
            $channelProvider,
            $channelId,
            $userId ? $request->header('x-fossabot-message-userdisplayname') : null,
            $userId ? $request->header('x-fossabot-message-userprovider') : null,
            $userId
        );
    }

    private function isValidFossabotToken(string $token): bool
    {
        try {
            return Http::acceptJson()->timeout(3)
                ->get('https://api.fossabot.com/v2/customapi/validate/'.rawurlencode($token))
                ->ok();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function testUser(): BotRequest
    {
        return new BotRequest('twitch', '49056910', 'xgerhard', 'twitch', '49056910');
    }

    private function isNightbot(Request $request): bool
    {
        $secret = config('services.cloudflare.secret');

        if (! $secret) {
            return $this->isNightbotIp((string) $request->ip());
        }

        // Cloudflare adds the secret, without it the request went around Cloudflare and the ip can be faked
        return hash_equals($secret, (string) $request->header('X-Origin-Secret'))
            && $this->isNightbotIp((string) $request->header('CF-Connecting-IP'));
    }

    private function isNightbotIp(string $ip): bool
    {
        $key = 'nightbot-ip:'.$ip;
        $isNightbot = Cache::get($key);

        if ($isNightbot === null) {
            $isNightbot = (int) $this->nbheaders->isNightbotIp($ip);
            Cache::put($key, $isNightbot, $isNightbot ? now()->addDay() : now()->addMinute());
        }

        return (bool) $isNightbot;
    }
}
