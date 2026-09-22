<?php

namespace App\Http\Controllers;

use App\Chat\Providers\NightbotApiClient;
use App\Chat\Providers\NightbotBotProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function index(NightbotBotProvider $nightbot)
    {
        $token = session('nightbot_api_token');

        if (! $token) {
            return redirect()->route('logout');
        }

        $commands = (new NightbotApiClient)->setAccessToken($token)->getCustomCommands();
        $expectedUrl = $nightbot->commandSnippet(Auth::user());

        $installedCommands = array_values(array_filter(
            $commands['commands'] ?? [],
            fn (array $command) => str_contains($command['message'], NightbotBotProvider::COMMAND_URL),
        ));

        $commandNeedsUpdate = ! empty($installedCommands)
            && ! collect($installedCommands)->contains(fn ($command) => $command['message'] === $expectedUrl);

        return view('dashboard', [
            'commands' => $installedCommands,
            'commandNeedsUpdate' => $commandNeedsUpdate,
            'commandSnippet' => $expectedUrl,
            'enabledProviders' => config('xgpt.enabled_providers'),
        ]);
    }

    public function save(Request $request, NightbotBotProvider $nightbot)
    {
        if ($request->has('install_command')) {
            $token = session('nightbot_api_token');

            if (! $token) {
                return redirect()->route('logout');
            }

            $nightbot->install(Auth::user(), $token);

            Session::flash('message', 'Command installed/updated!');
            Session::flash('alert-class', 'callout-block-success');

            return redirect()->route('dashboard');
        }

        $providers = collect(config('xgpt.enabled_providers'))->pluck('key');

        $validated = $request->validate([
            'instructions' => ['nullable', 'string', 'max:2000'],
            'ai_provider' => ['nullable', 'string', 'in:'.$providers->implode(',')],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();

        $user->settings()->updateOrCreate(['user_id' => $user->id], [
            'instructions' => $validated['instructions'] ?? null,
            'ai_provider' => $validated['ai_provider'] ?? null,
            // Only overwrite the stored key when a new one is actually submitted — an empty
            // field means "keep what's saved", not "clear it".
            ...($request->filled('ai_api_key') ? ['ai_api_key' => $validated['ai_api_key']] : []),
            'mention_user' => $request->boolean('mention_user'),
            'show_sponsor_heart' => $request->boolean('show_sponsor_heart'),
        ]);

        $user->update(['sponsor' => (bool) $validated['ai_provider']]);

        Session::flash('message', 'Successfully saved your settings!');
        Session::flash('alert-class', 'callout-block-success');

        return redirect()->route('dashboard');
    }
}
