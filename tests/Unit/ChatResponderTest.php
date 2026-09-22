<?php

use App\Ai\Agents\ChatResponder;
use App\Models\User;
use App\Models\UserSettings;
use Carbon\Carbon;
use Laravel\Ai\Enums\Lab;

function putMessageAt(User $channel, string $role, ?string $username, string $content, Carbon $at): void
{
    $message = $channel->messages()->create(compact('role', 'username', 'content'));
    DB::table('messages')->where('id', $message->id)->update(['created_at' => $at, 'updated_at' => $at]);
}

it('always includes the mandatory multi-user framing and length limit, even with no custom instructions', function () {
    $channel = User::factory()->create(['provider' => 'twitch']);

    $instructions = (string) (new ChatResponder($channel->fresh()))->instructions();

    expect($instructions)->toContain('[username]')
        ->and($instructions)->toContain('newest')
        ->and($instructions)->toContain('250 characters')
        ->and($instructions)->not->toContain("\n\n"); // nothing appended when there's no custom text
});

it('gives YouTube channels the shorter length limit in the framing', function () {
    $channel = User::factory()->create(['provider' => 'youtube']);

    expect((new ChatResponder($channel->fresh()))->instructions())->toContain('150 characters');
});

it('appends a channel\'s custom instructions after the mandatory framing, never in place of it', function () {
    $channel = User::factory()
        ->has(UserSettings::factory()->state(['instructions' => 'Answer like a sarcastic pirate.']), 'settings')
        ->create(['provider' => 'twitch']);

    $instructions = (string) (new ChatResponder($channel->fresh()))->instructions();

    expect($instructions)->toContain('[username]') // framing still present
        ->and($instructions)->toContain('250 characters')
        ->and($instructions)->toContain('Answer like a sarcastic pirate.') // custom text appended
        ->and($instructions)->toEndWith('Answer like a sarcastic pirate.');
});

it('ignores a custom instructions field that is only whitespace', function () {
    $channel = User::factory()
        ->has(UserSettings::factory()->state(['instructions' => "   \n  "]), 'settings')
        ->create(['provider' => 'twitch']);

    $instructions = (string) (new ChatResponder($channel->fresh()))->instructions();

    expect($instructions)->not->toContain("\n\n");
});

it('pins OpenAI to minimal reasoning effort', function () {
    $channel = User::factory()->create();
    $responder = new ChatResponder($channel->fresh());

    expect($responder->providerOptions('openai'))->toBe(['reasoning' => ['effort' => 'minimal']])
        ->and($responder->providerOptions(Lab::OpenAI))->toBe(['reasoning' => ['effort' => 'minimal']]);
});

it('sends no OpenAI-specific reasoning options to a different provider', function () {
    $channel = User::factory()->create();
    $responder = new ChatResponder($channel->fresh());

    expect($responder->providerOptions('anthropic'))->toBe([]);
});

it('windows history to the configured minutes and message cap, oldest first', function () {
    config(['xgpt.history.window_minutes' => 5, 'xgpt.history.max_messages' => 10]);

    $channel = User::factory()->create();
    $now = now();

    putMessageAt($channel, 'user', 'alice', 'too old', $now->copy()->subMinutes(6));
    putMessageAt($channel, 'user', 'bob', 'first in window', $now->copy()->subMinutes(4));
    putMessageAt($channel, 'assistant', null, 'a reply', $now->copy()->subMinutes(3));
    putMessageAt($channel, 'user', 'carol', 'most recent', $now->copy()->subMinute());

    $messages = (new ChatResponder($channel->fresh()))->messages();

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->content)->toBe('[bob] first in window')
        ->and($messages[1]->content)->toBe('a reply')
        ->and($messages[2]->content)->toBe('[carol] most recent');
});

it('caps the window at max_messages even when all are recent', function () {
    config(['xgpt.history.window_minutes' => 5, 'xgpt.history.max_messages' => 3]);

    $channel = User::factory()->create();
    $now = now();

    foreach (range(1, 5) as $i) {
        putMessageAt($channel, 'user', "user{$i}", "message {$i}", $now->copy()->subSeconds(50 - $i));
    }

    $messages = (new ChatResponder($channel->fresh()))->messages();

    expect($messages)->toHaveCount(3)
        ->and($messages[2]->content)->toBe('[user5] message 5');
});

it('never leaks one channel\'s history into another\'s', function () {
    $channelA = User::factory()->create();
    $channelB = User::factory()->create();

    putMessageAt($channelA, 'user', 'alice', 'a secret for channel A', now());
    putMessageAt($channelB, 'user', 'bob', 'unrelated message', now());

    $messages = (new ChatResponder($channelB->fresh()))->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->content)->toBe('[bob] unrelated message');
});

it('uses the free-tier OpenAI provider when no key is configured', function () {
    $channel = User::factory()->has(UserSettings::factory()->state(['ai_provider' => null, 'ai_api_key' => null]), 'settings')->create();

    $responder = new ChatResponder($channel->fresh());

    expect($responder->provider())->toBe('openai');
});

it('falls back to the free tier when the stored provider is no longer in the allowlist', function () {
    config(['xgpt.enabled_providers' => [
        ['key' => 'openai', 'label' => 'OpenAI', 'default_model' => 'gpt-5-nano', 'free_tier' => true, 'key_help_url' => '#'],
    ]]);

    $channel = User::factory()->has(UserSettings::factory()->state([
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-xxx',
    ]), 'settings')->create();

    $responder = new ChatResponder($channel->fresh());

    expect($responder->provider())->toBe('openai');
});

it('registers a dynamic per-channel provider when a BYO key is set for an allowlisted provider', function () {
    config(['xgpt.enabled_providers' => [
        ['key' => 'openai', 'label' => 'OpenAI', 'default_model' => 'gpt-5-nano', 'free_tier' => true, 'key_help_url' => '#'],
        ['key' => 'anthropic', 'label' => 'Anthropic', 'default_model' => 'claude-haiku-4-5', 'free_tier' => false, 'key_help_url' => '#'],
    ]]);

    $channel = User::factory()->has(UserSettings::factory()->state([
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'sk-ant-real-key',
    ]), 'settings')->create();

    $responder = new ChatResponder($channel->fresh());

    $dynamicName = $responder->provider();

    expect($dynamicName)->toBe('channel-'.$channel->id)
        ->and(config("ai.providers.{$dynamicName}.driver"))->toBe('anthropic')
        ->and(config("ai.providers.{$dynamicName}.key"))->toBe('sk-ant-real-key')
        ->and($responder->model())->toBe('claude-haiku-4-5');
});
