<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Models\User;

/**
 * Signs in a channel with Nightbot or Twitch, both find the same channel by its platform id
 */
class AuthController extends Controller
{
    public function redirectToNightbot()
    {
        return Socialite::driver('nightbot')->redirect();
    }

    public function handleNightbotCallback()
    {
        $nightbotUser = Socialite::driver('nightbot')->user();

        $this->login($nightbotUser['provider'], $nightbotUser['providerId'], $nightbotUser['name'], $nightbotUser['displayName']);

        // Only kept in the session, to install the command
        session(['nightbot_api_token' => $nightbotUser->token]);

        return redirect('/dashboard');
    }

    public function redirectToTwitch()
    {
        return Socialite::driver('twitch')->setScopes([])->redirect();
    }

    public function handleTwitchCallback()
    {
        $twitchUser = Socialite::driver('twitch')->user();

        $this->login('twitch', $twitchUser->getId(), $twitchUser['login'], $twitchUser->getName());

        return redirect('/dashboard');
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
