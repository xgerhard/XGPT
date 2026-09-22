<?php

use App\Chat\ChannelResolver;
use App\Models\User;

it('creates a new channel with empty (optional) settings on first contact', function () {
    $channel = (new ChannelResolver)->resolve('twitch', '123', 'SomeStreamer');

    expect($channel)->toBeInstanceOf(User::class)
        ->and($channel->provider)->toBe('twitch')
        ->and($channel->provider_id)->toBe('123')
        ->and($channel->display_name)->toBe('SomeStreamer')
        ->and($channel->token)->toHaveLength(8)
        ->and($channel->settings)->not->toBeNull()
        // The mandatory multi-user/length framing lives in ChatResponder, not here — see
        // ChatResponderTest for that coverage. A fresh channel's own instructions start empty.
        ->and($channel->settings->instructions)->toBeNull();
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
