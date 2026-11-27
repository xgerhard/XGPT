<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRequestTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false, 'services.cloudflare.secret' => null]);
        Http::preventStrayRequests();
    }

    private function answer(string $text = 'hello'): array
    {
        return ['api.openai.com/*' => Http::response(['output' => [[
            'type' => 'message', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ]]])];
    }

    protected function tearDown(): void
    {
        foreach ($this->headers as $name) {
            unset($_SERVER[$name]);
        }

        parent::tearDown();
    }

    // NBHeaders reads the headers from $_SERVER
    private function server(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $_SERVER[$name] = $value;
            $this->headers[] = $name;
        }
    }

    private function nightbotHeaders(): void
    {
        $this->server([
            'HTTP_NIGHTBOT_CHANNEL' => 'name=chan&displayName=Chan&provider=twitch&providerId=1',
            'HTTP_NIGHTBOT_USER' => 'name=bob&displayName=Bob&provider=twitch&providerId=2&userLevel=everyone',
        ]);
    }

    public function test_requests_without_bot_headers_are_blocked(): void
    {
        $this->get('/api/command?q=hi')->assertOk()->assertSee('Only verified bot requests allowed.');
        Http::assertNothingSent();
    }

    public function test_nightbot_request_from_a_nightbot_ip_is_answered(): void
    {
        Http::fake($this->answer());
        Cache::put('nightbot-ip:127.0.0.1', 1);
        $this->nightbotHeaders();

        $this->get('/api/command?q=hi')->assertSee('@Bob: hello');
    }

    private function fossabotHeaders(bool $withUser = true, string $provider = 'twitch'): void
    {
        $this->withHeaders([
            'x-fossabot-customapitoken' => 'token123',
            'x-fossabot-channelprovider' => $provider,
            'x-fossabot-channelproviderid' => '1',
            'x-fossabot-validateurl' => 'https://evil.example/validate',
        ] + ($withUser ? [
            'x-fossabot-message-userprovider' => $provider,
            'x-fossabot-message-userproviderid' => '2',
            'x-fossabot-message-userdisplayname' => 'Bob',
        ] : []));
    }

    public function test_fossabot_request_with_a_valid_token_is_answered(): void
    {
        Http::fake(['api.fossabot.com/*' => Http::response(['context_url' => 'https://api.fossabot.com/v2/customapi/context/token123'])] + $this->answer());
        $this->fossabotHeaders();

        $this->get('/api/command?q=hi')->assertSee('@Bob: hello');

        Http::assertSent(fn ($r) => $r->url() === 'https://api.fossabot.com/v2/customapi/validate/token123');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'evil.example'));
    }

    public function test_youtube_viewer_is_named_without_an_at(): void
    {
        Http::fake(['api.fossabot.com/*' => Http::response(['context_url' => 'x'])] + $this->answer());
        $this->fossabotHeaders(provider: 'youtube');

        $this->assertSame('Bob: hello', $this->get('/api/command?q=hi')->getContent());
    }

    public function test_fossabot_timer_has_no_viewer(): void
    {
        Http::fake(['api.fossabot.com/*' => Http::response(['context_url' => 'x'])] + $this->answer());
        $this->fossabotHeaders(withUser: false);

        $this->assertSame('hello', $this->get('/api/command?q=hi')->getContent());
    }

    public function test_fossabot_request_with_an_invalid_token_is_blocked(): void
    {
        Http::fake(['api.fossabot.com/*' => Http::response(['code' => 'token_invalid'], 400)] + $this->answer());
        $this->fossabotHeaders();

        $this->get('/api/command?q=hi')->assertSee('Only verified bot requests allowed.');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai'));
    }

    public function test_fossabot_request_is_blocked_when_fossabot_is_unreachable(): void
    {
        Http::fake(['api.fossabot.com/*' => Http::failedConnection()]);
        $this->fossabotHeaders();

        $this->get('/api/command?q=hi')->assertSee('Only verified bot requests allowed.');
    }

    public function test_fossabot_replies_are_one_message(): void
    {
        Http::fake([
            'api.fossabot.com/*' => Http::response(['context_url' => 'x']),
        ] + $this->answer(str_repeat('word ', 200)));
        $this->fossabotHeaders();
        $this->server(['HTTP_NIGHTBOT_RESPONSE_URL' => 'https://api.nightbot.tv/1/channel/send/x']);

        $reply = $this->get('/api/command?q=hi')->getContent();

        $this->assertSame(399, mb_strlen($reply));
        $this->assertStringEndsWith('...', $reply);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'nightbot'));
    }

    public function test_nightbot_headers_from_another_ip_are_blocked(): void
    {
        Cache::put('nightbot-ip:127.0.0.1', 0);
        $this->nightbotHeaders();

        $this->get('/api/command?q=hi')->assertSee('Only verified bot requests allowed.');
    }
}
