<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatResponder;
use App\Chat\BotProviderRegistry;
use App\Chat\ChannelResolver;
use App\Chat\Contracts\SupportsAutoInstall;
use App\Chat\IncomingMessage;
use App\Exceptions\InvalidTokenException;
use App\Models\User;
use App\Support\DailyRequestBudget;
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
    private const UNEXPECTED_ERROR = 'Something went wrong on XGPT\'s side. Please try again later.';

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
        DailyRequestBudget $budget,
    ) {
        $channel = null;
        $responder = null;

        try {
            $bot = $registry->resolve($request);
            $incoming = $bot->parse($request);

            if (! $this->hasTextContent($incoming->message)) {
                return Arr::random(self::NO_QUERY_REPLIES);
            }

            // Per-channel throttle, keyed by the *claimed* identity. Nightbot's headers carry no
            // cryptographic proof of origin (see NightbotBotProvider::matches()), so an attacker
            // can rotate the claimed provider_id to dodge this; it bounds damage from a single
            // spoofed identity, it isn't the only control. See the route-level per-IP throttle
            // for the coarser backstop.
            $channelRateLimitKey = "chat-command:{$incoming->provider}:{$incoming->providerId}";

            if (RateLimiter::tooManyAttempts($channelRateLimitKey, (int) config('xgpt.rate_limit.per_channel_per_minute'))) {
                return 'Slow down a bit! Try again in a moment.';
            }

            RateLimiter::hit($channelRateLimitKey, 60);

            $channel = $channels->resolve($incoming->provider, $incoming->providerId, $incoming->channelDisplayName);

            $responder = new ChatResponder($channel);

            if ($responder->usingOwnKey()) {
                $this->guardToken($channel, $request);

                // Counted only after the token check, so only token holders can use up a
                // channel's own limit.
                $dailyLimit = min(
                    $channel->settings->daily_request_limit ?? (int) config('xgpt.own_key.daily_request_limit'),
                    (int) config('xgpt.own_key.max_daily_request_limit'),
                );

                if (! $budget->consume("channel:{$channel->id}", $dailyLimit)) {
                    return 'This channel has used up its XGPT replies for today. Back tomorrow!';
                }
            } elseif (! $budget->consume('free-tier', (int) config('xgpt.free_tier.daily_request_limit'))) {
                return 'XGPT\'s free replies are used up for today. The streamer can add their own API key to keep going: https://xgpt.gerhard.dev/dashboard';
            }

            // Prompted before it's stored: ChatResponder::messages() reads this table fresh at
            // call time, and laravel/ai always appends the prompt() text as its own final message
            // on top of that history — storing first would make the newest message appear twice
            // in the request sent to the provider. Labeled with [username] via the same
            // formatLine() the history uses, so the newest message follows the same convention
            // FRAMING tells the model to expect, rather than being the one unlabeled line.
            $response = $responder->prompt(ChatResponder::formatLine($incoming->username, $incoming->message));

            if ($channel->remembersHistory()) {
                $channel->messages()->create([
                    'role' => 'user',
                    'username' => $incoming->username,
                    'content' => $incoming->message,
                ]);
            }

            return $this->deliver($channel, $incoming, $bot, (string) $response);
        } catch (RuntimeException) {
            return 'Only Nightbot requests allowed.';
        } catch (InvalidTokenException) {
            return 'This XGPT command is outdated or set up wrong. The streamer can fix it at https://xgpt.gerhard.dev/dashboard';
        } catch (InsufficientCreditsException) {
            return $responder?->usingOwnKey()
                ? 'The streamer\'s AI account has run out of credit or hit its spending limit. They can check it with their AI provider.'
                : 'XGPT\'s free pool has run out for this month. Help keep it running: https://github.com/sponsors/xgerhard ❤️';
        } catch (RateLimitedException) {
            return 'The AI service is busy right now. Try again in a moment.';
        } catch (RequestException $e) {
            // laravel/ai only wraps 429/402/5xx-family provider errors into its own typed
            // exceptions (see Gateway\Concerns\HandlesFailoverErrors) — a 401/403 (invalid key)
            // bubbles up as a raw Illuminate\Http\Client\RequestException instead.
            if (in_array($e->response?->status(), [401, 403], true) && $responder?->usingOwnKey()) {
                return 'The streamer\'s AI API key isn\'t working. They can update it at https://xgpt.gerhard.dev/dashboard';
            }

            Log::error('XGPT provider request failed.', ['status' => $e->response?->status(), 'channel_id' => $channel?->id]);

            return self::UNEXPECTED_ERROR;
        } catch (ProviderConnectionException) {
            return 'The AI took too long to answer. Try again in a moment.';
        } catch (ProviderOverloadedException) {
            return 'The AI service is having issues right now. Try again in a moment.';
        } catch (\Throwable $e) {
            // Deliberately broad: nothing this endpoint does should ever surface as an uncaught
            // exception (bots relay whatever text comes back verbatim into chat) — see
            // config/xgpt.php's neighboring CLAUDE.md section for why this is load-bearing.
            Log::error('XGPT command failed unexpectedly.', ['channel_id' => $channel?->id, 'exception' => $e->getMessage()]);

            return self::UNEXPECTED_ERROR;
        }
    }

    /**
     * Token-gate a channel that uses its own AI key (free-tier channels need no token by design —
     * see the security notes in CLAUDE.md).
     */
    private function guardToken(User $channel, Request $request): void
    {
        if (! $request->has('token') || $request->get('token') !== $channel->token) {
            throw new InvalidTokenException;
        }
    }

    /**
     * Store the reply, apply mention/length formatting, and split into a second message via the
     * bot's response URL when it doesn't fit in one. Lengths are in characters (mb_*), never
     * bytes: a byte cut shortens non-Latin replies and can split a character in half.
     */
    private function deliver(User $channel, IncomingMessage $incoming, $bot, string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $maxLength = $incoming->provider === 'youtube' ? 199 : 399;
        $mention = $channel->settings?->mention_user && $incoming->username
            ? ($this->hasSponsorHeart($incoming) ? '[❤️] ' : '')."@{$incoming->username}: "
            : '';

        if ($mention !== '') {
            $maxLength -= mb_strlen($mention);
        }

        if (mb_strlen($text) <= $maxLength) {
            $this->rememberReply($channel, $text);

            return $mention.$text;
        }

        $firstPart = mb_substr($text, 0, $maxLength - 3).'...';

        if ($incoming->responseUrl && $bot instanceof SupportsAutoInstall) {
            $secondMaxLength = $incoming->provider === 'youtube' ? 199 : 399;
            $secondPart = mb_substr($text, $maxLength - 3, $secondMaxLength);
            $stored = mb_substr($text, 0, ($maxLength - 3) + $secondMaxLength);

            $this->rememberReply($channel, $stored);

            // Deferred, not inline: by the time DeliverOverflowMessage::terminate() runs, Nightbot
            // has already received the first half.
            session(['xgpt_overflow' => ['response_url' => $incoming->responseUrl, 'message' => $secondPart]]);
        } else {
            $this->rememberReply($channel, mb_substr($text, 0, $maxLength - 3));
        }

        return $mention.$firstPart;
    }

    private function rememberReply(User $channel, string $content): void
    {
        if ($channel->remembersHistory()) {
            $channel->messages()->create(['role' => 'assistant', 'content' => $content]);
        }
    }

    /**
     * Whether the chatter's own XGPT channel earns a heart next to their name: a GitHub sponsor
     * (users.sponsor, set by hand) or a channel using its own API key, unless they turned it off.
     */
    private function hasSponsorHeart(IncomingMessage $incoming): bool
    {
        if (! $incoming->userProvider || ! $incoming->userProviderId) {
            return false;
        }

        $chatter = User::where('provider', $incoming->userProvider)
            ->where('provider_id', $incoming->userProviderId)
            ->first();

        if (! $chatter || $chatter->settings?->show_sponsor_heart === false) {
            return false;
        }

        return $chatter->sponsor || (new ChatResponder($chatter))->usingOwnKey();
    }

    private function hasTextContent(string $text): bool
    {
        $text = trim(preg_replace('/\s+/', '', $text));
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0E}\x{FE0F}\x{200D}\s]+/u', '', $text);

        return $text !== '';
    }
}
