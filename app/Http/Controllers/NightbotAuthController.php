<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Models\User;

class NightbotAuthController extends Controller
{
    public function redirectToProvider()
    {
        return Socialite::driver('nightbot')->redirect();
    }

    public function handleProviderCallback()
    {
        $nightbotUser = Socialite::driver('nightbot')->user();

        $user = User::where([
            ['provider', '=', $nightbotUser['provider']],
            ['provider_id', '=', $nightbotUser['providerId']]
        ])->first();

        if (!$user) {
            $user = User::create([
                'name' => $nightbotUser['name'],
                'display_name' => $nightbotUser['displayName'],
                'provider' => $nightbotUser['provider'],
                'provider_id' => $nightbotUser['providerId'],
                'token' => $this->generateToken()
            ]);
        }

        Auth::login($user);
        session(['nightbot_api_token' => $nightbotUser->token]);

        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function generateToken()
    {
        while (true) {
            $string = generate_random_string(
                8 // length
            );

            $user = User::where('token', $string)->first();
            if (!$user) {
                return $string;
            }
        }
    }
}