<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Log;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\src\NightbotAPI;
 
class CommandMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (isset($response->original)) {
            $conversation = substr($response->original, -3);
            if (session()->has($conversation)) {
                $extraMessage = session($conversation);
                session()->forget($conversation);
                $nightbotAPI = new NightbotAPI;
                $nightbotAPI->sendMessageByResponseUrl($extraMessage['responseUrl'], $extraMessage['message']);
            }
        }
    }
}