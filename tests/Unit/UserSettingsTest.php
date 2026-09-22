<?php

use App\Models\User;
use App\Models\UserSettings;

it('encrypts the API key at rest and decrypts it back via the accessor', function () {
    $user = User::factory()->create();
    $settings = UserSettings::factory()->for($user)->create(['ai_api_key' => 'sk-super-secret']);

    $raw = DB::table('user_settings')->where('id', $settings->id)->value('ai_api_key');

    expect($raw)->not->toBe('sk-super-secret')
        ->and($settings->fresh()->decrypted_ai_api_key)->toBe('sk-super-secret');
});

it('never exposes the encrypted key through array/JSON serialization', function () {
    $user = User::factory()->create();
    $settings = UserSettings::factory()->for($user)->create(['ai_api_key' => 'sk-super-secret']);

    expect($settings->toArray())->not->toHaveKey('ai_api_key')
        ->and($settings->toJson())->not->toContain('sk-super-secret');
});

it('returns null (not an exception) when the key cannot be decrypted', function () {
    $user = User::factory()->create();
    $settings = UserSettings::factory()->for($user)->create();

    DB::table('user_settings')->where('id', $settings->id)->update(['ai_api_key' => 'not-valid-ciphertext']);

    expect($settings->fresh()->decrypted_ai_api_key)->toBeNull();
});

it('treats an empty key as null rather than encrypting an empty string', function () {
    $user = User::factory()->create();
    $settings = UserSettings::factory()->for($user)->create(['ai_api_key' => null]);

    expect($settings->fresh()->decrypted_ai_api_key)->toBeNull();
});
