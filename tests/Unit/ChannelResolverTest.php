<?php

use App\Chat\ChannelResolver;
use App\Models\User;

it('creates a new channel with default settings on first contact', function () {
    $channel = (new ChannelResolver)->resolve('twitch', '123', 'SomeStreamer');

    expect($channel)->toBeInstanceOf(User::class)
        ->and($channel->provider)->toBe('twitch')
        ->and($channel->provider_id)->toBe('123')
        ->and($channel->display_name)->toBe('SomeStreamer')
        ->and($channel->token)->toHaveLength(8)
        ->and($channel->settings)->not->toBeNull()
        ->and($channel->settings->instructions)->toContain('Twitch chat')
        ->and($channel->settings->instructions)->toContain('250 characters');
});

it('gives YouTube channels a shorter default length instruction', function () {
    $channel = (new ChannelResolver)->resolve('youtube', '456', 'SomeStreamer');

    expect($channel->settings->instructions)->toContain('150 characters');
});

it('reuses the same row for a known (provider, provider_id) pair instead of duplicating', function () {
    $resolver = new ChannelResolver;

    $first = $resolver->resolve('twitch', '123', 'SomeStreamer');
    $second = $resolver->resolve('twitch', '123', 'SomeStreamer');

    expect($second->id)->toBe($first->id)
        ->and(User::count())->toBe(1);
});

it('keeps the display name fresh when a streamer renames', function () {
    $resolver = new ChannelResolver;

    $channel = $resolver->resolve('twitch', '123', 'OldName');
    $channel = $resolver->resolve('twitch', '123', 'NewName');

    expect($channel->display_name)->toBe('NewName')
        ->and(User::count())->toBe(1);
});
