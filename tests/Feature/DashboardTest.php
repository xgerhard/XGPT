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
    config(['xgpt.own_key.daily_request_limit' => 5000, 'xgpt.own_key.max_daily_request_limit' => 15000]);
    Http::fake(['api.nightbot.tv/*' => Http::response(['commands' => []])]);

    $channel = User::factory()->create();
    $channel->settings()->create(['instructions' => 'Talk like a pirate.']);

    $this->actingAs($channel)
        ->withSession(['nightbot_api_token' => 'fake-token'])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Talk like a pirate.')
        ->assertSee('Leave empty for the recommended 5,000. The maximum is 15,000.');
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
        ->and($settings->decrypted_ai_api_key)->toBe('sk-my-real-key');
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

it('rejects a daily reply limit outside 1 to the maximum', function (string $limit) {
    config(['xgpt.own_key.max_daily_request_limit' => 15000]);

    $this->actingAs(User::factory()->create())
        ->from('/dashboard')
        ->post('/dashboard', ['save_settings' => '1', 'daily_request_limit' => $limit])
        ->assertSessionHasErrors('daily_request_limit');
})->with(['zero' => '0', 'above the maximum' => '15001']);

it('accepts a daily reply limit at the maximum', function () {
    config(['xgpt.own_key.max_daily_request_limit' => 15000]);

    $this->actingAs(User::factory()->create())
        ->post('/dashboard', ['save_settings' => '1', 'daily_request_limit' => '15000'])
        ->assertSessionHasNoErrors();
});

it('never changes the manually set sponsor flag when saving settings', function () {
    $channel = User::factory()->create(['sponsor' => true]);

    $this->actingAs($channel)->post('/dashboard', ['save_settings' => '1', 'ai_provider' => '']);

    expect($channel->fresh()->sponsor)->toBeTrue();
});

it('saves the history settings', function () {
    $channel = User::factory()->create();

    $this->actingAs($channel)->post('/dashboard', [
        'save_settings' => '1',
        'history_max_messages' => '20',
        'history_window_minutes' => '15',
    ])->assertSessionHasNoErrors();

    $settings = $channel->settings()->firstOrFail();

    expect($settings->use_history)->toBeFalse()
        ->and($settings->history_max_messages)->toBe(20)
        ->and($settings->history_window_minutes)->toBe(15);
});

it('rejects history sizes above the maximum', function (string $field, string $value) {
    config(['xgpt.history.own_key_max_messages' => 30, 'xgpt.history.own_key_max_window_minutes' => 60]);

    $this->actingAs(User::factory()->create())
        ->from('/dashboard')
        ->post('/dashboard', ['save_settings' => '1', $field => $value])
        ->assertSessionHasErrors($field);
})->with([
    'messages' => ['history_max_messages', '31'],
    'minutes' => ['history_window_minutes', '61'],
]);
