<?php

namespace App\Ai\Agents;

use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class ChatResponder implements Agent, Conversational, HasProviderOptions
{
    use Promptable;

    /**
     * Always prepended to instructions() — never something a channel's own (optional) custom
     * text can override or lose. Two things this exists to guarantee:
     *
     * 1. The model knows the history it's about to read is a shared, multi-user window (lines
     *    labeled [username], not one continuous back-and-forth) and to answer only the newest
     *    message rather than trying to address everyone in the window at once.
     * 2. The hard chat-length constraint always applies, regardless of what a streamer writes
     *    in their own instructions field — a channel that clears/replaces it shouldn't lose the
     *    one guardrail that keeps replies from getting truncated mid-sentence.
     *
     * Kept deliberately short: this is sent as input on every single request, so its wording is
     * a real, ongoing cost, not a one-time one.
     */
    private const FRAMING = 'You are a helpful assistant in a %s chat. Viewers can ask you questions. '
        .'Messages are labeled [username]; answer only the newest one, using earlier messages as '
        .'context only if from the same user or clearly referenced. Answer directly; only ask a '
        .'question back if the message is too unclear to answer. Don\'t prefix replies with a name '
        .'or tag. Answer in %d characters or less.';

    public function __construct(private readonly User $channel) {}

    /**
     * The [username] labeling convention referenced in FRAMING — applied to every user-authored
     * line the model sees, including the current prompt (see CommandController::run()), so the
     * newest message follows the same convention as the history around it.
     */
    public static function formatLine(?string $username, string $content): string
    {
        return $username ? "[{$username}] {$content}" : $content;
    }

    public function instructions(): Stringable|string
    {
        $length = $this->channel->provider === 'youtube' ? 150 : 250;
        $framing = sprintf(
            self::FRAMING,
            $this->channel->provider === 'youtube' ? 'YouTube' : ucfirst($this->channel->provider),
            $length,
        );

        $custom = trim((string) $this->channel->settings?->instructions);

        return $custom === '' ? $framing : "{$framing}\n\n{$custom}";
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        if (! $this->channel->remembersHistory()) {
            return [];
        }

        $window = now()->subMinutes($this->historyWindowMinutes());

        // Ordered by id, not created_at: this table's timestamps are whole-second precision,
        // and a user message + its assistant reply are stored a few milliseconds apart — well
        // within the same second, so they (and any other messages created in that same second)
        // tie on created_at. id is a reliable, always-increasing proxy for insertion order that
        // created_at alone can't guarantee here.
        return $this->channel->messages()
            ->where('created_at', '>=', $window)
            ->latest('id')
            ->limit($this->historyMaxMessages())
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn ($message) => new Message(
                $message->role,
                self::formatLine($message->username, $message->content),
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

    /**
     * Caps what the provider generates (and bills), not just what we post: replies are cut to the
     * chat limit afterwards anyway, and a prompt asking for an essay would otherwise be paid for
     * in full. On reasoning models this budget includes reasoning tokens.
     */
    public function maxTokens(): int
    {
        return (int) config('xgpt.max_output_tokens');
    }

    public function timeout(): int
    {
        // Nightbot's own urlfetch gives up quickly — stay well under that so we always get to
        // answer (even with an error string) rather than Nightbot timing out on us first.
        return 8;
    }

    /**
     * Pins OpenAI's gpt-5-family models to minimal reasoning effort. Without this, a reasoning
     * model like gpt-5-nano defaults to a higher effort level: more reasoning tokens billed, more
     * latency, no reply-quality benefit here. Scoped to the openai driver only — other providers
     * have their own, differently-shaped reasoning controls.
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        return match ($driver) {
            'openai' => ['reasoning' => ['effort' => 'minimal']],
            default => [],
        };
    }

    /**
     * The channel's own history window if it uses its own key, capped by the owner's maximum;
     * free-tier channels always get the site default.
     */
    public function historyWindowMinutes(): int
    {
        return $this->ownKeyHistorySetting('history_window_minutes', 'window_minutes', 'own_key_max_window_minutes');
    }

    public function historyMaxMessages(): int
    {
        return $this->ownKeyHistorySetting('history_max_messages', 'max_messages', 'own_key_max_messages');
    }

    private function ownKeyHistorySetting(string $setting, string $default, string $max): int
    {
        $defaultValue = (int) config("xgpt.history.{$default}");
        $chosen = $this->usingOwnKey() ? $this->channel->settings->{$setting} : null;

        return $chosen ? min($chosen, (int) config("xgpt.history.{$max}")) : $defaultValue;
    }

    /**
     * Whether this channel's replies run on its own key rather than the site's free tier. The
     * single source of truth for that — CommandController's token gate and daily limits use it too.
     */
    public function usingOwnKey(): bool
    {
        $settings = $this->channel->settings;

        return (bool) ($settings
            && $settings->ai_provider
            && $settings->ai_provider === $this->allowlistedProvider()['key']
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
