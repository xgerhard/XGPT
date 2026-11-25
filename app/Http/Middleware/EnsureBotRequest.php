<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use xgerhard\nbheaders\NBHeaders;

class EnsureBotRequest
{
    public function __construct(private NBHeaders $nbheaders)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // With debug on, CommandController sets a test user so the command works from the browser
        if (config('app.debug') || $this->isNightbot($request)) {
            return $next($request);
        }

        return response('Only verified bot requests allowed.')->header('Content-Type', 'text/plain');
    }

    private function isNightbot(Request $request): bool
    {
        if (! $this->nbheaders->hasNightbotHeaders()) {
            return false;
        }

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
