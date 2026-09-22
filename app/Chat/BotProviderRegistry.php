<?php

namespace App\Chat;

use App\Chat\Contracts\BotProvider;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * All registered chat-bot integrations, tried in order. Adding a new bot (e.g. a future
 * Fossabot integration, which implements BotProvider only — no SupportsAutoInstall, since
 * Fossabot has no account-linking API) is: implement BotProvider, add it to the array bound in
 * App\Providers\AppServiceProvider. Nothing here or in CommandController changes.
 */
class BotProviderRegistry
{
    /** @param BotProvider[] $providers */
    public function __construct(private readonly array $providers) {}

    public function resolve(Request $request): BotProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->matches($request)) {
                return $provider;
            }
        }

        throw new RuntimeException('No registered bot provider recognized this request.');
    }
}
