<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBotRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

// Relies on APP_DEBUG=true, which makes CommandController fake the Nightbot headers.
class CommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeReply(string $text): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['output' => [[
            'type' => 'message',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ]]])]);
    }

    private function channel(): User
    {
        $user = User::create([
            'name' => 'xgerhard', 'display_name' => 'xgerhard',
            'provider' => 'twitch', 'provider_id' => '49056910',
        ]);
        $user->settings()->create([
            'start_instructions' => 'start',
            'show_conversation_id' => 1, 'mention_user' => 1, 'show_sponsor_heart' => 1,
        ]);

        return $user;
    }

    public function test_replies_and_continues_a_conversation(): void
    {
        $this->channel();
        $this->fakeReply('hello there');

        $reply = $this->get('/api/command?q=hi')->assertOk()->getContent();
        $id = Conversation::firstOrFail()->id;
        $this->assertSame("xgerhard: hello there #$id", $reply);

        $this->get('/api/command?q='.urlencode("#$id again"))->assertSee("#$id", false);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(4, Conversation::first()->messages()->count());
    }

    public function test_hides_the_conversation_id_by_default(): void
    {
        $this->fakeReply('hello there');

        $this->assertSame('xgerhard: hello there', $this->get('/api/command?q=hi')->getContent());
    }

    public function test_plus_continues_the_last_conversation_of_the_viewer(): void
    {
        $this->fakeReply('hello');
        $this->get('/api/command?q=one');
        $this->get('/api/command?q=two');

        $this->travel(110)->minutes();
        $this->get('/api/command?q='.urlencode('+ three'));
        $this->travel(110)->minutes();
        $this->get('/api/command?q='.urlencode('+ four'));

        $this->assertSame(2, Conversation::count());
        Http::assertSent(fn ($r) => array_column(array_slice($r['input'], 1, -1), 'content') === ['two', 'hello', 'three', 'hello', 'four']);
    }

    public function test_plus_only_continues_an_own_recent_conversation(): void
    {
        $this->fakeReply('hello');
        config(['app.debug' => false]);
        $this->withoutMiddleware(EnsureBotRequest::class);
        $ask = function ($channel, $user, $q) {
            $id = fn ($id) => "name=a&displayName=a&provider=twitch&providerId=$id";
            $_SERVER['HTTP_NIGHTBOT_CHANNEL'] = $id($channel);
            $_SERVER['HTTP_NIGHTBOT_USER'] = $id($user).'&userLevel=everyone';
            $this->get('/api/command?q='.urlencode($q))->assertSee('hello');
        };

        try {
            $ask(1, 1, 'hi');
            $ask(1, 2, '+ other viewer');
            $ask(2, 1, '+ other channel');
            $this->assertSame(3, Conversation::count());

            $ask(1, 1, '+ own');
            $this->assertSame(3, Conversation::count());

            $this->travel(121)->minutes();
            $ask(1, 1, '+ too late');
            $this->assertSame(4, Conversation::count());
        } finally {
            unset($_SERVER['HTTP_NIGHTBOT_CHANNEL'], $_SERVER['HTTP_NIGHTBOT_USER']);
        }

        config(['app.debug' => true]);
        $this->get('/api/command?q='.urlencode('+'))->assertSee('Missing query');
    }

    public function test_plus_starts_a_new_conversation_when_the_last_one_is_deleted(): void
    {
        $this->fakeReply('hello');
        $this->get('/api/command?q=hi');
        Conversation::query()->delete();

        $this->get('/api/command?q='.urlencode('+ hi'))->assertSee('hello');
        $this->assertSame(1, Conversation::count());
    }

    public function test_cleanup_deletes_conversations_unused_for_two_hours(): void
    {
        $this->channel();
        $this->fakeReply('hello');
        $this->get('/api/command?q=unused');
        $unused = Conversation::firstOrFail()->id;
        $this->get('/api/command?q=used');
        $used = Conversation::whereKeyNot($unused)->firstOrFail()->id;

        $this->travel(110)->minutes();
        $this->get('/api/command?q='.urlencode("#$used again"));
        $this->travel(110)->minutes();
        $this->artisan('xgpt:cleanup');

        $this->assertSame([$used], Conversation::pluck('id')->all());
        $this->assertDatabaseMissing('conversation_messages', ['conversation_id' => $unused]);
    }

    public function test_plus_inside_a_word_is_part_of_the_question(): void
    {
        $this->fakeReply('8');
        $this->get('/api/command?q='.urlencode('+5 +3'));

        $this->assertDatabaseHas('conversation_messages', ['content' => '+5 +3']);
    }

    public function test_missing_query(): void
    {
        $this->get('/api/command')->assertOk()->assertSee('Missing query');
    }

    public function test_sends_the_channel_prompt_and_the_length_rule(): void
    {
        $this->channel()->settings()->update(['end_instructions' => 'custom']);
        $this->fakeReply('hello');

        $this->get('/api/command?q=hi')->assertSee('hello');

        Http::assertSent(fn ($r) => array_column($r['input'], 'content') === ['start', 'hi', 'Keep the reply to one or two short sentences, at most 300 characters.']);
    }

    public function test_stored_key_and_token_are_ignored(): void
    {
        config(['services.openai.key' => 'sk-site']);
        $this->channel()->settings()->update(['api_key' => 'old-encrypted-key']);
        $this->fakeReply('hello');

        $this->get('/api/command?q=hi')->assertSee('hello');
        $this->get('/api/command?q=hi&token=abcd1234')->assertSee('hello');
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($r) => ! $r->hasHeader('Authorization', 'Bearer sk-site'));
    }

    public function test_saving_settings_keeps_sponsor_and_stored_key(): void
    {
        $user = $this->channel();
        $user->update(['sponsor' => 1]);
        $user->settings()->update(['api_key' => 'old-encrypted-key']);

        $this->actingAs($user)->post('/dashboard', ['save_settings' => 1, 'api_key' => 'x', 'mention_user' => 'on'])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'sponsor' => 1]);
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id, 'api_key' => 'old-encrypted-key']);
    }

    public function test_installs_the_command_without_a_token(): void
    {
        Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => [['_id' => '1', 'name' => '!other', 'message' => 'x']]])]);

        $this->actingAs($this->channel())->withSession(['nightbot_api_token' => 't'])
            ->post('/dashboard', ['action' => 'install_command', 'command_code' => '!ai']);

        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['message'] === '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))');
    }

    public function test_installs_the_first_command_of_a_channel(): void
    {
        Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => []])]);

        $this->actingAs($this->channel())->withSession(['nightbot_api_token' => 't'])
            ->post('/dashboard', ['action' => 'install_command', 'command_code' => '!ai']);

        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['name'] === '!ai');
    }

    public function test_openai_quota_error_is_chat_text(): void
    {
        Http::fake(['*' => Http::response(['error' => ['type' => 'insufficient_quota']], 429)]);

        $this->get('/api/command?q=hi')->assertOk()->assertSee('monthly usage limit');
    }

    public function test_pages_and_auth_redirect(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertStringStartsWith('https://api.nightbot.tv/oauth2/authorize', $this->get('/login')->headers->get('Location'));
    }

    public function test_first_login_creates_the_channel(): void
    {
        $nightbotUser = (new SocialiteUser)->setToken('t')->setRaw([
            'name' => 'new', 'displayName' => 'New', 'provider' => 'twitch', 'providerId' => '1',
        ]);
        Socialite::shouldReceive('driver->user')->andReturn($nightbotUser);

        $this->get('/login/nightbot/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['provider_id' => '1', 'token' => null]);
    }

    public function test_dashboard_shows_an_installed_command(): void
    {
        Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => [[
            '_id' => '1', 'name' => '!mychat', 'coolDown' => 5, 'userLevel' => 'everyone',
            'message' => '$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring)&token=abcd1234)',
        ]]])]);

        $this->actingAs($this->channel())->withSession(['nightbot_api_token' => 't'])
            ->get('/dashboard')->assertOk()->assertSee('!mychat');
    }

    public function test_dashboard_loads_and_saves_settings(): void
    {
        Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => []])]);
        $user = $this->channel();

        $this->actingAs($user)->withSession(['nightbot_api_token' => 't'])->get('/dashboard')->assertOk();
        $this->post('/dashboard', ['save_settings' => 1, 'start_instructions' => 's'])->assertRedirect('/dashboard');
        $this->assertSame('s', $user->fresh()->settings->start_instructions);
    }
}
