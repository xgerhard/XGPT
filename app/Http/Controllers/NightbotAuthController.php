<?php

namespace App\Http\Controllers;

use App\Chat\ChannelResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class NightbotAuthController extends Controller
{
    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('nightbot')->redirect();
    }

    public function handleProviderCallback(ChannelResolver $channels): RedirectResponse
    {
        $nightbotUser = Socialite::driver('nightbot')->user();

        $channel = $channels->resolve(
            $nightbotUser['provider'],
            $nightbotUser['providerId'],
            $nightbotUser['displayName'],
        );

        Auth::login($channel);

        // Only ever kept in session, never persisted — see App\Http\Controllers\
        // DashboardController and CLAUDE.md's security notes on why.
        session(['nightbot_api_token' => $nightbotUser->token]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
