<?php

use App\Ai\Agents\ChatResponder;
use App\Chat\IncomingMessage;
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

it('rejects incomplete Nightbot headers', function (array $headers) {
    $this->withHeaders($headers)
        ->get('/api/command?q=hello')
        ->assertOk()
        ->assertSee('Only Nightbot requests allowed.', false);
})->with([
    'channel missing name' => [[
        'Nightbot-Channel' => 'displayName=SomeChannel&provider=twitch&providerId=999',
    ]],
    'channel with unrelated fields only' => [[
        'Nightbot-Channel' => 'foo=bar',
    ]],
    'user missing userLevel' => [[
        'Nightbot-Channel' => 'name=somechannel&displayName=SomeChannel&provider=twitch&providerId=999',
        'Nightbot-User' => 'name=chatter&displayName=chatter&provider=twitch&providerId=1',
    ]],
]);

it('accepts a timer request, which has no Nightbot-User header', function () {
    ChatResponder::fake(['timer reply']);

    $this->withHeaders(['Nightbot-Channel' => 'name=somechannel&displayName=SomeChannel&provider=twitch&providerId=999'])
        ->get('/api/command?q=hello')
        ->assertOk()
        ->assertSee('timer reply', false);
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

it('stops an own-key channel at its daily limit, counting only token-verified requests', function () {
    ChatResponder::fake(['first reply', 'should not be reached']);

    $channel = User::factory()->create(['provider' => 'twitch', 'provider_id' => '1001', 'token' => 'TOKEN1001']);
    $channel->settings()->create(['ai_provider' => 'openai', 'ai_api_key' => 'sk-real-key', 'daily_request_limit' => 1]);

    // A request with a wrong token is rejected before it can use up the limit.
    $this->withHeaders(nightbotHeaders(providerId: '1001'))
        ->get('/api/command?q=hi&token=WRONG')
        ->assertSee('token is missing', false);

    $this->withHeaders(nightbotHeaders(providerId: '1001'))
        ->get('/api/command?q=hi&token=TOKEN1001')
        ->assertSee('first reply', false);

    $this->withHeaders(nightbotHeaders(providerId: '1001'))
        ->get('/api/command?q=again&token=TOKEN1001')
        ->assertSee('reached its daily XGPT limit', false);
});

it('ignores a daily limit on a free-tier channel', function () {
    ChatResponder::fake(['one', 'two']);

    $channel = User::factory()->create(['provider' => 'twitch', 'provider_id' => '1002']);
    $channel->settings()->create(['daily_request_limit' => 1]);

    $this->withHeaders(nightbotHeaders(providerId: '1002'))->get('/api/command?q=hi')->assertSee('one', false);
    $this->withHeaders(nightbotHeaders(providerId: '1002'))->get('/api/command?q=hi')->assertSee('two', false);
});

it('answers the per-IP throttle with a plain chat sentence instead of an error page', function () {
    config(['xgpt.rate_limit.per_ip_per_minute' => 1]);
    ChatResponder::fake(['ok']);

    $this->withHeaders(nightbotHeaders(providerId: '1003'))->get('/api/command?q=hi')->assertSee('ok', false);

    $response = $this->withHeaders(nightbotHeaders(providerId: '1004'))->get('/api/command?q=hi');

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe('XGPT is getting a lot of requests right now. Try again in a moment.');
});

it('limits per caller IP from Cloudflare\'s CF-Connecting-IP header', function () {
    config(['xgpt.rate_limit.per_ip_per_minute' => 1]);
    ChatResponder::fake(['a', 'b']);

    $fromCaller = fn (string $ip) => $this->withHeaders([...nightbotHeaders(providerId: '1005'), 'CF-Connecting-IP' => $ip]);

    // Every request arrives from the same Cloudflare edge, but each caller gets its own bucket.
    $fromCaller('198.51.100.1')->get('/api/command?q=hi')->assertSee('a', false);
    $fromCaller('198.51.100.2')->get('/api/command?q=hi')->assertSee('b', false);
    $fromCaller('198.51.100.1')->get('/api/command?q=hi')->assertSee('lot of requests', false);
});

it('strips a length instruction appended to the query by an older command setup', function (string $query) {
    ChatResponder::fake(['a reply']);

    $this->withHeaders(nightbotHeaders(providerId: '1100'))->get("/api/command?q={$query}");

    expect(Message::where('role', 'user')->value('content'))->toBe('what is a cat');
})->with([
    'plain' => ['what%20is%20a%20cat%20answer%20in%20150%20characters%20or%20less'],
    'doubled %% encoding' => ['what%20is%20a%20cat%20answer%20in%20150%%2020characters%20or%20less'],
    'truncated "les"' => ['what%20is%20a%20cat%20answer%20in%20150%%2020characters%20or%20les'],
    'capitalised with a period' => ['what%20is%20a%20cat%20Answer%20in%20200%20characters%20or%20less.'],
    'keep all replies under' => ['what%20is%20a%20cat%20keep%20all%20replies%20under%20150%20characters.'],
]);

it('keeps a length request that is part of the actual question', function () {
    ChatResponder::fake(['a reply']);

    $this->withHeaders(nightbotHeaders(providerId: '1101'))
        ->get('/api/command?q=can%20you%20answer%20in%20150%20characters%20or%20less%20about%20cats');

    expect(Message::where('role', 'user')->value('content'))
        ->toBe('can you answer in 150 characters or less about cats');
});

it('treats a query that is only the length instruction as empty', function () {
    $response = $this->withHeaders(nightbotHeaders(providerId: '1102'))
        ->get('/api/command?q=answer%20in%20150%20characters%20or%20less');

    expect(CommandController::NO_QUERY_REPLIES)->toContain($response->getContent());
});

it('caps an own-key channel at the configured default when it has not set a limit', function () {
    config(['xgpt.own_key.daily_request_limit' => 1]);
    ChatResponder::fake(['first reply', 'should not be reached']);

    $channel = User::factory()->create(['provider' => 'twitch', 'provider_id' => '1200', 'token' => 'TOKEN1200']);
    $channel->settings()->create(['ai_provider' => 'openai', 'ai_api_key' => 'sk-real-key']);

    $this->withHeaders(nightbotHeaders(providerId: '1200'))
        ->get('/api/command?q=hi&token=TOKEN1200')
        ->assertSee('first reply', false);

    $this->withHeaders(nightbotHeaders(providerId: '1200'))
        ->get('/api/command?q=again&token=TOKEN1200')
        ->assertSee('reached its daily XGPT limit', false);
});

it('cuts an oversized query before it is sent or stored', function () {
    $capturedPrompt = null;
    ChatResponder::fake(function (string $prompt) use (&$capturedPrompt) {
        $capturedPrompt = $prompt;

        return 'a reply';
    });

    $this->withHeaders(nightbotHeaders(providerId: '1300', username: 'eve'))
        ->get('/api/command?q='.rawurlencode(str_repeat('é', 5000)));

    $capped = str_repeat('é', IncomingMessage::MAX_MESSAGE_LENGTH);

    expect($capturedPrompt)->toBe("[eve] {$capped}")
        ->and(Message::where('role', 'user')->value('content'))->toBe($capped);
});

it('cuts an oversized username', function () {
    ChatResponder::fake(['a reply']);

    $this->withHeaders(nightbotHeaders(providerId: '1301', username: str_repeat('x', 500)))
        ->get('/api/command?q=hi');

    expect(Message::where('role', 'user')->value('username'))
        ->toBe(str_repeat('x', IncomingMessage::MAX_USERNAME_LENGTH));
});
