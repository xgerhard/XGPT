<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('redirects a guest to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('redirects to logout if the Nightbot session token is missing', function () {
    $channel = User::factory()->create();

    $this->actingAs($channel)
        ->get('/dashboard')
        ->assertRedirect(route('logout'));
});

it('renders the dashboard with the channel\'s saved settings', function () {
    Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => []])]);

    $channel = User::factory()->create();
    $channel->settings()->create(['instructions' => 'Talk like a pirate.']);

    $this->actingAs($channel)
        ->withSession(['nightbot_api_token' => 'fake-token'])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Talk like a pirate.');
});

it('rejects a provider outside the owner\'s allowlist', function () {
    config(['xgpt.enabled_providers' => [
        ['key' => 'openai', 'label' => 'OpenAI', 'default_model' => 'gpt-5-nano', 'free_tier' => true, 'key_help_url' => '#'],
    ]]);

    $channel = User::factory()->create();

    $this->actingAs($channel)
        ->from('/dashboard')
        ->post('/dashboard', [
            'save_settings' => '1',
            'ai_provider' => 'some-unlisted-provider',
        ])
        ->assertSessionHasErrors('ai_provider');
});

it('saves settings and encrypts a submitted API key', function () {
    config(['xgpt.enabled_providers' => [
        ['key' => 'openai', 'label' => 'OpenAI', 'default_model' => 'gpt-5-nano', 'free_tier' => true, 'key_help_url' => '#'],
    ]]);

    $channel = User::factory()->create();

    $this->actingAs($channel)->post('/dashboard', [
        'save_settings' => '1',
        'instructions' => 'Be extra concise.',
        'ai_provider' => 'openai',
        'ai_api_key' => 'sk-my-real-key',
        'mention_user' => '1',
    ])->assertRedirect(route('dashboard'));

    $settings = $channel->settings()->firstOrFail();

    expect($settings->instructions)->toBe('Be extra concise.')
        ->and($settings->ai_provider)->toBe('openai')
        ->and($settings->decrypted_ai_api_key)->toBe('sk-my-real-key')
        ->and($channel->fresh()->sponsor)->toBeTrue();
});

it('keeps the saved API key when the field is left blank on a later save', function () {
    $channel = User::factory()->create();
    $channel->settings()->create(['ai_provider' => 'openai', 'ai_api_key' => 'sk-original-key']);

    $this->actingAs($channel)->post('/dashboard', [
        'save_settings' => '1',
        'instructions' => 'Updated instructions only.',
        'ai_provider' => 'openai',
        'ai_api_key' => '',
    ]);

    expect($channel->settings()->first()->decrypted_ai_api_key)->toBe('sk-original-key');
});

it('saves an optional daily reply limit', function () {
    $channel = User::factory()->create();

    $this->actingAs($channel)->post('/dashboard', [
        'save_settings' => '1',
        'daily_request_limit' => '250',
    ])->assertRedirect(route('dashboard'));

    expect($channel->settings()->firstOrFail()->daily_request_limit)->toBe(250);

    $this->actingAs($channel)->post('/dashboard', [
        'save_settings' => '1',
        'daily_request_limit' => '',
    ]);

    expect($channel->settings()->firstOrFail()->daily_request_limit)->toBeNull();
});

it('rejects a daily reply limit below 1', function () {
    $this->actingAs(User::factory()->create())
        ->from('/dashboard')
        ->post('/dashboard', ['save_settings' => '1', 'daily_request_limit' => '0'])
        ->assertSessionHasErrors('daily_request_limit');
});
