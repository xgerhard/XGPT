<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatResponder;
use App\Chat\BotProviderRegistry;
use App\Chat\ChannelResolver;
use App\Chat\Contracts\SupportsAutoInstall;
use App\Exceptions\InvalidTokenException;
use App\Models\User;
use App\Support\FreeTierBudgetGuard;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;

class CommandController extends Controller
{
    /**
     * Shown when Nightbot relayed an empty (or emoji-only) query — kept varied and light since,
     * for a lot of chatters, this is the very first thing the bot ever says to them. Picked at
     * random each time; referenced directly from CommandEndpointTest rather than duplicated there.
     */
    public const NO_QUERY_REPLIES = [
        'How can I help you today?',
        "Hey, what's up? Ask me something!",
        "Bleep bloop, I'm here. What do you need?",
        "I'm listening. Go ahead, ask away.",
        "Ready when you are. What's the question?",
        'Beep. Awaiting input. Try asking me something!',
        "Didn't catch a question there, try again?",
        'Give me something to work with! Ask me anything.',
        'Booting up... now ask me something.',
        'You rang? What can I do for you?',
        '*robot noises* Go on, ask your question.',
        "I've got answers, you've got to ask a question first.",
        "Say the word and I'll do my best to help.",
        "Still here, still listening. What's on your mind?",
    ];

    public function run(
        Request $request,
        BotProviderRegistry $registry,
        ChannelResolver $channels,
        FreeTierBudgetGuard $budgetGuard,
    ) {
        $channel = null;

        try {
            $bot = $registry->resolve($request);
            $incoming = $bot->parse($request);

            if (! $this->hasTextContent($incoming->message)) {
                return Arr::random(self::NO_QUERY_REPLIES);
            }

            // Per-channel throttle, keyed by the *claimed* identity. Nightbot's headers carry no
            // cryptographic proof of origin (confirmed against nbheaders' source — see
            // NightbotBotProvider), so an attacker can rotate the claimed provider_id to dodge
            // this; it bounds damage from a single spoofed identity, it isn't the only control.
            // See the route-level per-IP throttle for the coarser backstop.
            $channelRateLimitKey = "chat-command:{$incoming->provider}:{$incoming->providerId}";

            if (RateLimiter::tooManyAttempts($channelRateLimitKey, (int) config('xgpt.rate_limit.per_channel_per_minute'))) {
                return 'Slow down a bit! Try again in a moment.';
            }

            RateLimiter::hit($channelRateLimitKey, 60);

            $channel = $channels->resolve($incoming->provider, $incoming->providerId, $incoming->channelDisplayName);

            $this->guardToken($channel, $request);

            $responder = new ChatResponder($channel);

            if (! $this->usingOwnKey($channel) && ! $budgetGuard->consume()) {
                return 'XGPT has reached its free daily usage limit. Add your own API key in the dashboard to keep going: https://xgpt.gerhard.dev/dashboard';
            }

            $channel->messages()->create([
                'role' => 'user',
                'username' => $incoming->username,
                'content' => $incoming->message,
            ]);

            $response = $responder->prompt($incoming->message);

            return $this->deliver($channel, $incoming, $bot, (string) $response);
        } catch (RuntimeException) {
            return 'Only Nightbot requests allowed.';
        } catch (InvalidTokenException) {
            return 'Error: XGPT token is missing or doesn\'t match. Please visit https://xgpt.gerhard.dev/dashboard to resolve this issue.';
        } catch (InsufficientCreditsException|RateLimitedException) {
            return 'Error: The AI provider for this channel has hit its usage limit. Support this project: https://github.com/sponsors/xgerhard ❤️';
        } catch (RequestException $e) {
            // laravel/ai only wraps 429/402/5xx-family provider errors into its own typed
            // exceptions (see Gateway\Concerns\HandlesFailoverErrors) — a 401/403 (invalid key)
            // bubbles up as a raw Illuminate\Http\Client\RequestException instead.
            if (in_array($e->response?->status(), [401, 403], true)) {
                return 'Error: Incorrect API key provided in your XGPT settings. Check the dashboard for where to find a new one.';
            }

            Log::error('XGPT provider request failed.', ['status' => $e->response?->status(), 'channel_id' => $channel?->id]);

            return 'Error: Unexpected error occurred, please contact xgerhard.';
        } catch (ProviderConnectionException) {
            return 'Error: The AI provider took too long to respond. Please try again in a bit. ResidentSleeper';
        } catch (ProviderOverloadedException) {
            return 'Error: The AI provider is having issues right now. Please try again shortly.';
        } catch (\Throwable $e) {
            // Deliberately broad: nothing this endpoint does should ever surface as an uncaught
            // exception (bots relay whatever text comes back verbatim into chat) — see
            // config/xgpt.php's neighboring CLAUDE.md section for why this is load-bearing.
            Log::error('XGPT command failed unexpectedly.', ['channel_id' => $channel?->id, 'exception' => $e->getMessage()]);

            return 'Error: Unexpected error occurred, please contact xgerhard.';
        }
    }

    /**
     * Token-gate a channel once it's set its own AI key (unset on the free tier by design — see
     * config/xgpt.php and the Security & abuse-prevention notes in CLAUDE.md).
     */
    private function guardToken(User $channel, Request $request): void
    {
        if (! $this->usingOwnKey($channel)) {
            return;
        }

        if (! $request->has('token') || $request->get('token') !== $channel->token) {
            throw new InvalidTokenException;
        }
    }

    private function usingOwnKey(User $channel): bool
    {
        return (bool) ($channel->settings?->ai_provider && $channel->settings?->decrypted_ai_api_key);
    }

    /**
     * Store the reply, apply mention/length formatting, and split into a second message via the
     * bot's response URL when the reply doesn't fit — porting the pre-rewrite two-message trick.
     */
    private function deliver(User $channel, $incoming, $bot, string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $maxLength = $incoming->provider === 'youtube' ? 199 : 399;
        $mention = $channel->settings?->mention_user && $incoming->username ? "{$incoming->username}: " : '';

        if ($mention !== '') {
            $maxLength -= strlen($mention);
        }

        if (strlen($text) <= $maxLength) {
            $channel->messages()->create(['role' => 'assistant', 'content' => $text]);

            return $mention.$text;
        }

        $firstPart = substr($text, 0, $maxLength - 3).'...';

        if ($incoming->responseUrl && $bot instanceof SupportsAutoInstall) {
            $secondMaxLength = $incoming->provider === 'youtube' ? 199 : 399;
            $secondPart = substr($text, $maxLength - 3, $secondMaxLength);
            $stored = substr($text, 0, ($maxLength - 3) + $secondMaxLength);

            $channel->messages()->create(['role' => 'assistant', 'content' => $stored]);

            // Deferred, not inline: Nightbot has already received the first half by the time this
            // runs (see CommandMiddleware::terminate()), matching the pre-rewrite two-message flow.
            session(['xgpt_overflow' => ['response_url' => $incoming->responseUrl, 'message' => $secondPart]]);
        } else {
            $channel->messages()->create(['role' => 'assistant', 'content' => substr($text, 0, $maxLength - 3)]);
        }

        return $mention.$firstPart;
    }

    private function hasTextContent(string $text): bool
    {
        $text = trim(preg_replace('/\s+/', '', $text));
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0E}\x{FE0F}\x{200D}\s]+/u', '', $text);

        return $text !== '';
    }
}
