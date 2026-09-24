<?php

use App\Chat\Providers\NightbotBotProvider;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeNightbotCommands(array $commands): void
{
    Http::fake([
        'api.nightbot.tv/1/commands' => Http::response(['commands' => $commands]),
        'api.nightbot.tv/1/commands/*' => Http::response([]),
    ]);
}

it('wraps the command URL in $(urlfetch ...)', function () {
    $channel = User::factory()->create(['token' => 'TOKEN1']);

    expect((new NightbotBotProvider)->commandSnippet($channel))
        ->toBe('$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)');
});

it('adds the command when the channel has none', function () {
    fakeNightbotCommands([]);
    $channel = User::factory()->create(['token' => 'TOKEN1']);

    (new NightbotBotProvider)->install($channel, 'access-token');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['message'] === '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)'
        && $request['name'] === 'ai');
});

it('leaves an up-to-date command untouched', function () {
    $channel = User::factory()->create(['token' => 'TOKEN1']);
    fakeNightbotCommands([['_id' => 'abc', 'message' => (new NightbotBotProvider)->commandSnippet($channel)]]);

    (new NightbotBotProvider)->install($channel, 'access-token');

    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PUT'], true));
});

it('rewrites a command that points at XGPT in any other form', function (string $message) {
    fakeNightbotCommands([['_id' => 'abc', 'message' => $message]]);
    $channel = User::factory()->create(['token' => 'TOKEN1']);

    (new NightbotBotProvider)->install($channel, 'access-token');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/1/commands/abc')
        && $request['message'] === '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)');
})->with([
    'bare URL, not fetched' => ['https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1'],
    'old token' => ['$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=OLD)'],
]);

it('shows a correctly installed command as up to date on the dashboard', function () {
    $channel = User::factory()->create(['token' => 'TOKEN1']);
    fakeNightbotCommands([['_id' => 'abc', 'message' => '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)']]);

    $this->actingAs($channel)
        ->withSession(['nightbot_api_token' => 'access-token'])
        ->get('/dashboard')
        ->assertSee('Installed and up to date.');
});
