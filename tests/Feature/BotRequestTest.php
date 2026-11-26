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
        Http::fake(['api.openai.com/*' => Http::response(['output' => [[
            'type' => 'message', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => 'hello']],
        ]]])]);
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
        Cache::put('nightbot-ip:127.0.0.1', 1);
        $this->nightbotHeaders();

        $this->get('/api/command?q=hi')->assertSee('Bob: hello');
    }

    public function test_nightbot_headers_from_another_ip_are_blocked(): void
    {
        Cache::put('nightbot-ip:127.0.0.1', 0);
        $this->nightbotHeaders();

        $this->get('/api/command?q=hi')->assertSee('Only verified bot requests allowed.');
    }
}
