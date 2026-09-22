<?php

namespace App\Providers;

use App\Chat\BotProviderRegistry;
use App\Chat\Providers\NightbotBotProvider;
use App\Socialite\NightbotExtend;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bot integrations, tried in this order. Adding a future bot (e.g. Fossabot, which has
        // no account-linking API — see App\Chat\Contracts\SupportsAutoInstall) is: implement
        // BotProvider, add an instance here. Nothing else changes.
        //
        // Deliberately NOT a singleton: NightbotBotProvider memoizes the parsed headers for one
        // request (see its $headers property) — a singleton registry would hand out the same
        // provider instance, and therefore the same memoized headers, across unrelated requests.
        $this->app->bind(BotProviderRegistry::class, fn () => new BotProviderRegistry([
            $this->app->make(NightbotBotProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Authenticate::redirectUsing(fn () => route('login'));

        Event::listen(SocialiteWasCalled::class, [NightbotExtend::class, 'handle']);

        // Coarse per-IP backstop on /api/command, independent of the per-channel limiter inside
        // CommandController (which is keyed by a claimed, spoofable identity — see there).
        RateLimiter::for('chat-command-ip', fn (Request $request) => Limit::perMinute(
            (int) config('xgpt.rate_limit.per_ip_per_minute')
        )->by($request->ip()));
    }
}
