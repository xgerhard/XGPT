<?php

namespace Tests\Feature;

use App\Http\Controllers\CommandController;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->assertMatchesRegularExpression('/^[a-z]{3}$/', $id);
        $this->assertSame("@xgerhard: hello there #$id", $reply);

        $this->get('/api/command?q='.urlencode("#$id again"))->assertSee("#$id", false);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(4, Conversation::first()->messages()->count());
    }

    public function test_hides_the_conversation_id_by_default(): void
    {
        $this->fakeReply('hello there');

        $this->assertSame('@xgerhard: hello there', $this->get('/api/command?q=hi')->getContent());
    }

    public function test_sends_the_rest_of_a_long_reply_to_the_response_url(): void
    {
        $answer = str_repeat('नमस्ते ', 90).'end';
        $url = 'https://api.nightbot.tv/1/channel/send/test';
        $this->fakeReply($answer);
        Http::fake(['api.nightbot.tv/*' => Http::response([])]);
        $_SERVER['HTTP_NIGHTBOT_CHANNEL'] = 'name=a&displayName=a&provider=twitch&providerId=1';
        $_SERVER['HTTP_NIGHTBOT_RESPONSE_URL'] = $url;

        try {
            $first = $this->get('/api/command?q=hi')->getContent();
        } finally {
            unset($_SERVER['HTTP_NIGHTBOT_CHANNEL'], $_SERVER['HTTP_NIGHTBOT_RESPONSE_URL']);
        }

        $this->assertSame(399, mb_strlen($first));
        Http::assertSent(fn ($r) => $r->url() === $url && str_ends_with($r['message'], 'end'));
        $this->assertDatabaseHas('conversation_messages', ['role' => 'assistant', 'content' => $answer]);
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

    public function test_without_a_question_replies_without_asking_the_ai(): void
    {
        Http::preventStrayRequests();

        foreach (['/api/command', '/api/command?q=', '/api/command?q=%2B'] as $url) {
            $reply = $this->get($url)->assertOk()->getContent();
            $this->assertStringStartsWith('@xgerhard: ', $reply);
            $this->assertContains(substr($reply, strlen('@xgerhard: ')), CommandController::NO_QUESTION_REPLIES);
        }

        $this->channel()->settings()->update(['mention_user' => 0]);
        $this->assertContains($this->get('/api/command')->getContent(), CommandController::NO_QUESTION_REPLIES);

        $this->assertSame('', trim($this->get('/api/command?q='.urlencode('😂 👍'))->getContent()));
        Http::assertNothingSent();
    }

    public function test_sends_the_channel_prompt_and_the_length_rule(): void
    {
        $this->channel()->settings()->update(['end_instructions' => 'custom']);
        $this->fakeReply('hello');

        $this->get('/api/command?q=hi')->assertSee('hello');

        Http::assertSent(fn ($r) => array_column($r['input'], 'content') === ['start', 'hi', 'Keep the reply to one or two short sentences, at most 300 characters.']);
    }

    public function test_empty_prompt_uses_the_default(): void
    {
        $this->channel()->settings()->update(['start_instructions' => null]);
        $this->fakeReply('hello');

        $this->get('/api/command?q=hi')->assertSee('hello');

        Http::assertSent(fn ($r) => $r['input'][0]['content'] === 'You are a helpful assistant for a Twitch chat.');
    }

    public function test_saved_prompt_is_at_most_500_characters(): void
    {
        $user = $this->channel();
        $save = fn ($text) => $this->actingAs($user)->post('/settings', ['save_settings' => 1, 'start_instructions' => $text]);

        $save(str_repeat('न', 600));
        $this->assertSame(str_repeat('न', 500), $user->settings()->first()->start_instructions);

        // Browsers count a line break as one character, but send two
        $save("a\r\n".str_repeat('x', 498));
        $this->assertSame("a\n".str_repeat('x', 498), $user->settings()->first()->start_instructions);

        $save('');
        $this->assertSame('', $user->settings()->first()->start_instructions);
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

        $this->actingAs($user)->post('/settings', ['save_settings' => 1, 'api_key' => 'x', 'mention_user' => 'on'])->assertRedirect('/settings');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'sponsor' => 1]);
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id, 'api_key' => 'old-encrypted-key']);
    }

    public function test_openai_quota_error_is_chat_text(): void
    {
        Http::fake(['*' => Http::response(['error' => ['type' => 'insufficient_quota']], 429)]);

        $this->get('/api/command?q=hi')->assertOk()->assertSee('monthly usage limit');
    }

    public function test_other_openai_errors_are_chat_text_and_logged(): void
    {
        Exceptions::fake();
        Http::fake(['*' => Http::response(['error' => ['type' => 'invalid_request_error', 'message' => 'nope']], 400)]);

        $this->get('/api/command?q=hi')->assertOk()->assertSee('Unknown error');
        Exceptions::assertReported(fn (\RuntimeException $e) => str_contains($e->getMessage(), 'nope'));
    }

    public function test_timeout_is_chat_text_and_logged(): void
    {
        Log::spy();
        Http::fake(['*' => Http::failedConnection('timed out')]);

        $this->get('/api/command?q=hi')->assertOk()->assertSee('took too long');
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_answer_that_reached_the_token_limit_is_used(): void
    {
        Http::fake(['*' => Http::response(['status' => 'incomplete', 'output' => [[
            'type' => 'message',
            'status' => 'incomplete',
            'content' => [['type' => 'output_text', 'text' => 'half an answer']],
        ]]])]);

        $this->get('/api/command?q=hi')->assertSee('half an answer');
        Http::assertSent(fn ($r) => $r['max_output_tokens'] === 500);
    }

    public function test_empty_answer_is_an_error(): void
    {
        Exceptions::fake();
        $this->fakeReply('');

        $this->get('/api/command?q=hi')->assertSee('Unknown error');
        $this->assertDatabaseMissing('conversation_messages', ['role' => 'assistant']);
    }

    public function test_pages_and_auth_redirect(): void
    {
        $this->get('/')->assertOk();
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee(['/login/nightbot', '/login/twitch', '/login/kick']);
        $this->assertStringStartsWith('https://api.nightbot.tv/oauth2/authorize', $this->get('/login/nightbot')->headers->get('Location'));
        $this->assertStringStartsWith('https://id.twitch.tv/oauth2/authorize', $twitch = $this->get('/login/twitch')->headers->get('Location'));
        $this->assertStringNotContainsString('user%3Aread%3Aemail', $twitch);
        $kick = $this->get('/login/kick')->headers->get('Location');
        $this->assertStringStartsWith('https://id.kick.com/oauth/authorize', $kick);
        $this->assertStringContainsString('code_challenge=', $kick);
        $this->get('/login/other')->assertNotFound();
    }

    public function test_first_login_creates_the_channel(): void
    {
        $nightbotUser = (new SocialiteUser)->setToken('t')->setRaw([
            'name' => 'new', 'displayName' => 'New', 'provider' => 'twitch', 'providerId' => '1',
        ]);
        Socialite::shouldReceive('driver->user')->andReturn($nightbotUser);

        $this->get('/login/nightbot/callback')->assertRedirect('/settings');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['provider_id' => '1', 'token' => null]);
    }

    public function test_twitch_login_finds_the_same_channel(): void
    {
        $channel = $this->channel();
        $twitchUser = (new SocialiteUser)->setRaw(['login' => 'xgerhard'])->map(['id' => '49056910', 'name' => 'xgerhard']);
        Socialite::shouldReceive('driver->user')->andReturn($twitchUser);

        $this->get('/login/twitch/callback')->assertRedirect('/settings');

        $this->assertAuthenticatedAs($channel);
        $this->assertSame(1, User::count());
    }

    public function test_twitch_login_creates_a_channel(): void
    {
        $twitchUser = (new SocialiteUser)->setRaw(['login' => 'newchan'])->map(['id' => '5', 'name' => 'NewChan']);
        Socialite::shouldReceive('driver->user')->andReturn($twitchUser);

        $this->get('/login/twitch/callback');

        $this->assertDatabaseHas('users', ['provider' => 'twitch', 'provider_id' => '5', 'name' => 'newchan', 'display_name' => 'NewChan']);
    }

    public function test_kick_login_creates_a_channel(): void
    {
        $kickUser = (new SocialiteUser)->setRaw(['name' => 'kickchan'])->map(['id' => '7', 'nickname' => 'kickchan', 'name' => 'kickchan']);
        Socialite::shouldReceive('driver->user')->andReturn($kickUser);

        $this->get('/login/kick/callback')->assertRedirect('/settings');

        $this->assertDatabaseHas('users', ['provider' => 'kick', 'provider_id' => '7', 'name' => 'kickchan']);
    }

    public function test_install_shows_both_commands_and_settings_saves_settings(): void
    {
        Http::preventStrayRequests();
        $user = $this->channel();

        $this->actingAs($user)->get('/settings')->assertOk();
        $this->get('/install')->assertOk()
            ->assertSee('!commands add !ai -cd=5 $(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))', false)
            ->assertSee('!addcmd -u 5 ai $(customapi https://xgpt.gerhard.dev/api/command?q=$(querystring))', false);

        $this->post('/settings', ['save_settings' => 1, 'start_instructions' => 'new'])->assertRedirect('/settings');
        $this->assertSame('new', $user->settings()->first()->start_instructions);
    }
    public function test_settings_loads_and_saves_settings(): void
    {
        $user = $this->channel();

        $this->actingAs($user)->get('/settings')->assertOk();
        $this->post('/settings', ['save_settings' => 1, 'start_instructions' => 's'])->assertRedirect('/settings');
        $this->assertSame('s', $user->fresh()->settings->start_instructions);
    }
}
