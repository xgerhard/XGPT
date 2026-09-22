<?php

use App\Ai\Agents\ChatResponder;
use App\Models\Message;
use App\Models\User;

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

it('asks for a query when q is missing or empty', function () {
    $this->withHeaders(nightbotHeaders())
        ->get('/api/command')
        ->assertOk()
        ->assertSee('Missing query', false);
});

it('answers a free-tier request and stores both sides of the exchange', function () {
    ChatResponder::fake(['Hello there!']);

    $this->withHeaders(nightbotHeaders(providerId: '111', username: 'alice'))
        ->get('/api/command?q=hi+bot')
        ->assertOk()
        ->assertSee('alice: Hello there!', false);

    $channel = User::where('provider_id', '111')->firstOrFail();

    expect(Message::where('user_id', $channel->id)->where('role', 'user')->where('content', 'hi bot')->exists())->toBeTrue()
        ->and(Message::where('user_id', $channel->id)->where('role', 'assistant')->where('content', 'Hello there!')->exists())->toBeTrue();
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
