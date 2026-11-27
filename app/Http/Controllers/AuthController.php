<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Models\User;

/**
 * Signs in a channel with Nightbot, Twitch or Kick, both find the same channel by its platform id
 */
class AuthController extends Controller
{
    // As few permissions as each platform allows, Kick requires one to read the user
    public const SCOPES = [
        'twitch' => [],
        'kick' => ['user:read'],
    ];

    public function redirectToNightbot()
    {
        return Socialite::driver('nightbot')->redirect();
    }

    public function handleNightbotCallback()
    {
        $nightbotUser = Socialite::driver('nightbot')->user();

        $this->login($nightbotUser['provider'], $nightbotUser['providerId'], $nightbotUser['name'], $nightbotUser['displayName']);

        return redirect('/settings');
    }

    public function redirectToPlatform(string $platform)
    {
        return Socialite::driver($platform)->setScopes(self::SCOPES[$platform])->redirect();
    }

    public function handlePlatformCallback(string $platform)
    {
        $platformUser = Socialite::driver($platform)->user();

        $this->login($platform, $platformUser->getId(), $platformUser['login'] ?? $platformUser->getNickname(), $platformUser->getName());

        return redirect('/settings');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function login(string $provider, string $providerId, string $name, string $displayName): void
    {
        $user = User::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $providerId],
            ['name' => $name, 'display_name' => $displayName]
        );

        Auth::login($user);
    }
}
