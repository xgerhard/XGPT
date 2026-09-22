<?php

namespace App\Ai\Agents;

use App\Chat\ChannelResolver;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class ChatResponder implements Agent, Conversational
{
    use Promptable;

    public function __construct(private readonly User $channel) {}

    public function instructions(): Stringable|string
    {
        return $this->channel->settings?->instructions
            ?: app(ChannelResolver::class)->defaultInstructions($this->channel->provider);
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        $window = now()->subMinutes((int) config('xgpt.history.window_minutes'));

        return $this->channel->messages()
            ->where('created_at', '>=', $window)
            ->latest('created_at')
            ->limit((int) config('xgpt.history.max_messages'))
            ->get()
            ->sortBy('created_at')
            ->values()
            ->map(fn ($message) => new Message(
                $message->role,
                $message->role === 'user' && $message->username
                    ? "[{$message->username}] {$message->content}"
                    : $message->content,
            ))
            ->all();
    }

    /**
     * Resolved at call time (per laravel/ai's convention of defining a `provider()` method on
     * the Agent for runtime-dependent configuration). Free tier unless the channel has both
     * chosen a provider *and* supplied their own key for it, and that provider is still in the
     * owner's allowlist.
     */
    public function provider(): string
    {
        if (! $this->usingOwnKey()) {
            return 'openai';
        }

        $dynamicName = 'channel-'.$this->channel->id;

        // Process-local runtime provider registration — confirmed against the installed
        // laravel/ai source (AiManager::getInstanceConfig() reads config('ai.providers.*')
        // fresh on every resolution). Safe under classic PHP-FPM; would need to become
        // request-scoped rather than a global config() mutation before this app could adopt
        // Laravel Octane's persistent workers.
        config(["ai.providers.{$dynamicName}" => [
            'driver' => $this->allowlistedProvider()['key'],
            'key' => $this->channel->settings->decrypted_ai_api_key,
        ]]);

        return $dynamicName;
    }

    public function model(): string
    {
        return $this->allowlistedProvider()['default_model'];
    }

    public function timeout(): int
    {
        // Nightbot's own urlfetch gives up quickly — stay well under that so we always get to
        // answer (even with an error string) rather than Nightbot timing out on us first.
        return 8;
    }

    private function usingOwnKey(): bool
    {
        $settings = $this->channel->settings;

        return (bool) ($settings
            && $settings->ai_provider
            && $settings->ai_provider === $this->allowlistedProvider()['key']
            && $settings->ai_provider !== $this->freeTierProvider()['key']
            && $settings->decrypted_ai_api_key);
    }

    /**
     * The channel's chosen provider if it's still in the owner's allowlist, else the free-tier
     * default — config('xgpt.enabled_providers') can shrink over time and a channel's stored
     * choice shouldn't start erroring when that happens.
     */
    private function allowlistedProvider(): array
    {
        $chosenKey = $this->channel->settings?->ai_provider;

        if (! $chosenKey) {
            return $this->freeTierProvider();
        }

        return $this->enabledProviders()->firstWhere('key', $chosenKey) ?? $this->freeTierProvider();
    }

    private function freeTierProvider(): array
    {
        return $this->enabledProviders()->firstWhere('free_tier', true);
    }

    private function enabledProviders(): Collection
    {
        return collect(config('xgpt.enabled_providers'));
    }
}
