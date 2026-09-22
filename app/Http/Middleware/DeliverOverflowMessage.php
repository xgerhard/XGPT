<?php

namespace App\Http\Middleware;

use App\Chat\Providers\NightbotApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the second half of an over-length reply (see CommandController::deliver()) *after* the
 * first half has already been returned to the caller — Nightbot-only for now, matching the
 * pre-rewrite CommandMiddleware. A future bot that also needs this would get its own delivery
 * here, keyed off which BotProvider produced the overflow.
 */
class DeliverOverflowMessage
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $overflow = session('xgpt_overflow');

        if (! $overflow) {
            return;
        }

        session()->forget('xgpt_overflow');

        (new NightbotApiClient)->sendMessageByResponseUrl($overflow['response_url'], $overflow['message']);
    }
}
