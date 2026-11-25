<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
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

    private function channel(string $apiKey = ''): User
    {
        $user = User::create([
            'name' => 'xgerhard', 'display_name' => 'xgerhard',
            'provider' => 'twitch', 'provider_id' => '49056910', 'token' => 'abcd1234',
        ]);
        $user->settings()->create([
            'api_key' => $apiKey ? Crypt::encryptString($apiKey) : '',
            'start_instructions' => 'start', 'end_instructions' => 'end',
            'show_conversation_id' => 1, 'mention_user' => 1, 'show_sponsor_heart' => 1,
        ]);

        return $user;
    }

    public function test_replies_and_continues_a_conversation(): void
    {
        $this->fakeReply('hello there');

        $reply = $this->get('/api/command?q=hi')->assertOk()->getContent();
        $id = Conversation::firstOrFail()->id;
        $this->assertSame("xgerhard: hello there #$id", $reply);

        $this->get('/api/command?q='.urlencode("#$id again"))->assertSee("#$id", false);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(4, Conversation::first()->messages()->count());
    }

    public function test_missing_query(): void
    {
        $this->get('/api/command')->assertOk()->assertSee('Missing query');
    }

    public function test_own_key_requires_matching_token(): void
    {
        $this->channel('sk-own');
        $this->fakeReply('secret');

        $this->get('/api/command?q=hi')->assertSee('token is missing');
        $this->get('/api/command?q=hi&token=abcd1234')->assertSee('secret');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk-own'));
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

    public function test_dashboard_loads_and_saves_settings(): void
    {
        Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => []])]);
        $user = $this->channel();

        $this->actingAs($user)->withSession(['nightbot_api_token' => 't'])->get('/dashboard')->assertOk();
        $this->post('/dashboard', ['save_settings' => 1, 'api_key' => 'sk-new', 'start_instructions' => 's'])->assertRedirect('/dashboard');
        $this->assertSame('sk-new', $user->fresh()->settings->api_key);
    }
}
