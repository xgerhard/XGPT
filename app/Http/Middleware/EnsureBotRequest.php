<?php

namespace App\Http\Middleware;

use App\Services\Bots\BotRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $bot = $this->nightbot($request, $verify) ?? ($verify ? null : $this->testUser());

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
