<?php

use App\Ai\Agents\ChatResponder;
use App\Http\Controllers\CommandController;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;

function nightbotHeaders(string $providerId = '999', string $username = 'chatter', string $userLevel = 'everyone'): array
{
    return [
        'Nightbot-Channel' => "name=somechannel&displayName=SomeChannel&provider=twitch&providerId={$providerId}",
        'Nightbot-User' => "name={$username}&displayName={$username}&provider=twitch&providerId=1&userLevel={$userLevel}",
    ];
}

it('rejects a request with no Nightbot headers at all', function () {
    config(['app.debug' => false]);

    $this->get('/api/command?q=hello')
        ->assertOk()
        ->assertSee('Only Nightbot requests allowed.', false);
});

it('asks for a query when q is missing or empty, with one of the varied replies', function () {
    $response = $this->withHeaders(nightbotHeaders())->get('/api/command');

    $response->assertOk();
    expect(CommandController::NO_QUERY_REPLIES)->toContain($response->getContent());
});

it('answers a free-tier request and stores both sides of the exchange', function () {
    ChatResponder::fake(['Hello there!']);

    $this->withHeaders(nightbotHeaders(providerId: '111', username: 'alice'))
        ->get('/api/command?q=hi+bot')
        ->assertOk()
        ->assertSee('alice: Hello there!', false);

    $channel = User::where('provider_id', '111')->firstOrFail();

    expect(Message::where('channel_id', $channel->id)->where('role', 'user')->where('content', 'hi bot')->exists())->toBeTrue()
        ->and(Message::where('channel_id', $channel->id)->where('role', 'assistant')->where('content', 'Hello there!')->exists())->toBeTrue();
});

it('does not require a token for a free-tier channel', function () {
    ChatResponder::fake(['fine without a token']);

    $this->withHeaders(nightbotHeaders(providerId: '222'))
        ->get('/api/command?q=hi')
        ->assertOk()
        ->assertDontSee('token is missing', false);
});

it('requires a matching token once the channel has its own AI key', function () {
    ChatResponder::fake(['should not be reached']);

    $channel = User::factory()->create(['provider' => 'twitch', 'provider_id' => '333', 'token' => 'TOKEN123']);
    $channel->settings()->create(['ai_provider' => 'openai', 'ai_api_key' => 'sk-real-key']);

    $this->withHeaders(nightbotHeaders(providerId: '333'))
        ->get('/api/command?q=hi')
        ->assertOk()
        ->assertSee('token is missing', false);

    $this->withHeaders(nightbotHeaders(providerId: '333'))
        ->get('/api/command?q=hi&token=WRONG')
        ->assertOk()
        ->assertSee('token is missing', false);

    $this->withHeaders(nightbotHeaders(providerId: '333'))
        ->get('/api/command?q=hi&token=TOKEN123')
        ->assertOk()
        ->assertDontSee('token is missing', false);
});

it('throttles a single claimed channel identity past the per-minute limit', function () {
    config(['xgpt.rate_limit.per_channel_per_minute' => 2]);
    ChatResponder::fake(['ok', 'ok']);

    $headers = nightbotHeaders(providerId: '444');

    $this->withHeaders($headers)->get('/api/command?q=one')->assertSee('ok', false);
    $this->withHeaders($headers)->get('/api/command?q=two')->assertSee('ok', false);
    $this->withHeaders($headers)->get('/api/command?q=three')->assertSee('Slow down', false);
});

it('stops serving the free tier once the daily budget is exhausted', function () {
    config(['xgpt.free_tier.daily_request_limit' => 1, 'xgpt.rate_limit.per_channel_per_minute' => 100]);
    ChatResponder::fake(['ok']);

    $this->withHeaders(nightbotHeaders(providerId: '555'))->get('/api/command?q=one')->assertSee('ok', false);
    $this->withHeaders(nightbotHeaders(providerId: '666'))->get('/api/command?q=two')->assertSee('daily usage limit', false);
});

it('splits an over-length reply: trims the first message and defers the rest via Nightbot-Response-Url', function () {
    Http::fake([
        'api.nightbot.tv/*' => Http::response(['status' => 'ok']),
    ]);

    $longReply = str_repeat('a', 500);
    ChatResponder::fake([$longReply]);

    $headers = nightbotHeaders(providerId: '321', username: 'Y');
    $headers['Nightbot-Response-Url'] = 'https://api.nightbot.tv/1/commands/overflow-test';

    $response = $this->withHeaders($headers)->get('/api/command?q=hi');

    // First message: mention + as much of the reply as fits in 399 chars, ending in "...".
    $first = $response->getContent();
    expect(strlen($first))->toBe(399)
        ->and($first)->toStartWith('Y: ')
        ->and($first)->toEndWith('...');

    // Second message: deferred, sent to Nightbot's own response URL only — never an
    // attacker-controlled host (see NightbotBotProvider::safeResponseUrl()).
    Http::assertSentCount(1);
    Http::assertSent(fn (HttpClientRequest $request) => $request->url() === 'https://api.nightbot.tv/1/commands/overflow-test'
        && str_starts_with($request['message'], 'a')
    );

    // The full reply (both halves combined) is what's stored for history, not just the
    // first message's truncated-with-ellipsis text.
    $channel = User::where('provider_id', '321')->firstOrFail();
    $stored = Message::where('channel_id', $channel->id)->where('role', 'assistant')->first();
    expect(strlen($stored->content))->toBe(500)
        ->and($stored->content)->toBe($longReply);
});

it('refuses to deliver the overflow message to a spoofed, non-Nightbot response URL', function () {
    Http::fake();

    ChatResponder::fake([str_repeat('a', 500)]);

    $headers = nightbotHeaders(providerId: '654');
    $headers['Nightbot-Response-Url'] = 'https://attacker.example.com/collect';

    $this->withHeaders($headers)->get('/api/command?q=hi');

    Http::assertNothingSent();
});

it('prompts the agent before storing the incoming message, so it is not sent twice', function () {
    $countWhenPrompted = null;

    ChatResponder::fake(function () use (&$countWhenPrompted) {
        // Runs synchronously partway through prompt(), after ChatResponder::messages() has
        // already been queried for this same request — so this count reflects exactly what
        // that query saw.
        $countWhenPrompted = Message::count();

        return 'a reply';
    });

    $this->withHeaders(nightbotHeaders(providerId: '741'))->get('/api/command?q=hello');

    expect($countWhenPrompted)->toBe(0)
        ->and(Message::count())->toBe(2); // stored after: 1 user message, 1 assistant reply
});

it('labels the current prompt with [username], matching the history convention', function () {
    $capturedPrompt = null;

    ChatResponder::fake(function (string $prompt) use (&$capturedPrompt) {
        $capturedPrompt = $prompt;

        return 'a reply';
    });

    $this->withHeaders(nightbotHeaders(providerId: '852', username: 'dave'))
        ->get('/api/command?q=whats+the+weather');

    expect($capturedPrompt)->toBe('[dave] whats the weather');
});

it('does not spend the free-tier budget for a BYO-key channel', function () {
    config(['xgpt.free_tier.daily_request_limit' => 1]);
    ChatResponder::fake(['byo key reply', 'still within free tier', 'exhausted']);

    $channel = User::factory()->create(['provider' => 'twitch', 'provider_id' => '777', 'token' => 'TOKEN777']);
    $channel->settings()->create(['ai_provider' => 'openai', 'ai_api_key' => 'sk-real-key']);

    // The BYO-key request should NOT touch the shared free-tier budget...
    $this->withHeaders(nightbotHeaders(providerId: '777'))
        ->get('/api/command?q=one&token=TOKEN777')
        ->assertSee('byo key reply', false);

    // ...so a fresh free-tier channel still gets its (first and only) free request.
    $this->withHeaders(nightbotHeaders(providerId: '888'))
        ->get('/api/command?q=two')
        ->assertSee('still within free tier', false);

    // Now the free tier really is exhausted, for a third, different free-tier channel.
    $this->withHeaders(nightbotHeaders(providerId: '999'))
        ->get('/api/command?q=three')
        ->assertSee('daily usage limit', false);
});
