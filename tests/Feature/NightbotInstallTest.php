<?php

use App\Chat\Providers\NightbotBotProvider;
use App\Exceptions\CommandNameTakenException;
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

    (new NightbotBotProvider)->install($channel, 'access-token', '!ai');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['message'] === '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)'
        && $request['name'] === '!ai');
});

it('leaves an up-to-date command untouched', function () {
    $channel = User::factory()->create(['token' => 'TOKEN1']);
    fakeNightbotCommands([['_id' => 'abc', 'message' => (new NightbotBotProvider)->commandSnippet($channel)]]);

    (new NightbotBotProvider)->install($channel, 'access-token', '!ai');

    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PUT'], true));
});

it('rewrites a command that points at XGPT in any other form', function (string $message) {
    fakeNightbotCommands([['_id' => 'abc', 'message' => $message]]);
    $channel = User::factory()->create(['token' => 'TOKEN1']);

    (new NightbotBotProvider)->install($channel, 'access-token', '!ai');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/1/commands/abc')
        && $request['message'] === '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1)');
})->with([
    'bare URL, not fetched' => ['https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=TOKEN1'],
    'old token' => ['$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=OLD)'],
]);

it('refuses to overwrite an unrelated command with the chosen name', function () {
    fakeNightbotCommands([['_id' => 'abc', 'name' => '!AI', 'message' => 'Something else entirely']]);
    $channel = User::factory()->create(['token' => 'TOKEN1']);

    expect(fn () => (new NightbotBotProvider)->install($channel, 'access-token', '!ai'))
        ->toThrow(CommandNameTakenException::class);

    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PUT'], true));
});

function dashboardFor(User $channel)
{
    return test()->actingAs($channel)->withSession(['nightbot_api_token' => 'access-token'])->get('/dashboard');
}

it('offers a name input defaulting to !ai when no command is installed', function () {
    fakeNightbotCommands([]);

    dashboardFor(User::factory()->create())
        ->assertSee('No XGPT command found')
        ->assertSee('value="!ai"', false)
        ->assertSee('Install command')
        ->assertDontSee('Update command');
});

it('names an out-of-date command and offers only an update', function () {
    fakeNightbotCommands([['_id' => 'abc', 'name' => '!gpt', 'message' => 'https://xgpt.gerhard.dev/api/command?q=$(querystring)']]);

    dashboardFor(User::factory()->create())
        ->assertSee('We found your command <code>!gpt</code>, but it\'s out of date.', false)
        ->assertSee('Update command')
        ->assertDontSee('id="command_name"', false);
});

it('only confirms an up-to-date command, without buttons or install instructions', function () {
    $channel = User::factory()->create(['token' => 'TOKEN1']);
    fakeNightbotCommands([['_id' => 'abc', 'name' => '!ai', 'message' => (new NightbotBotProvider)->commandSnippet($channel)]]);

    dashboardFor($channel)
        ->assertSee('Your command <code>!ai</code> is installed and up to date.', false)
        ->assertDontSee('Update command')
        ->assertDontSee('Install command')
        ->assertDontSee('paste this manually');
});

it('installs under the submitted command name', function () {
    fakeNightbotCommands([]);

    $this->actingAs(User::factory()->create())
        ->withSession(['nightbot_api_token' => 'access-token'])
        ->post('/dashboard', ['install_command' => '1', 'command_name' => '!askai'])
        ->assertRedirect(route('dashboard'));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['name'] === '!askai');
});

it('shows an error instead of installing when the name is taken or invalid', function (string $name, string $error) {
    fakeNightbotCommands([['_id' => 'abc', 'name' => '!ai', 'message' => 'Something else entirely']]);

    $this->actingAs(User::factory()->create())
        ->withSession(['nightbot_api_token' => 'access-token'])
        ->from('/dashboard')
        ->post('/dashboard', ['install_command' => '1', 'command_name' => $name])
        ->assertRedirect('/dashboard')
        ->assertSessionHasErrors(['command_name' => $error]);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
})->with([
    'taken' => ['!ai', 'You already have a different command with that name. Pick another name.'],
    'contains a space' => ['!ask ai', 'The command name can\'t contain spaces.'],
]);
