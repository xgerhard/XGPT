<?php

namespace App\Http\Controllers;

use App\Chat\Providers\NightbotApiClient;
use App\Chat\Providers\NightbotBotProvider;
use App\Exceptions\CommandNameTakenException;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function index(NightbotBotProvider $nightbot, #[CurrentUser] User $user)
    {
        $token = session('nightbot_api_token');

        if (! $token) {
            return redirect()->route('logout');
        }

        $commands = (new NightbotApiClient)->setAccessToken($token)->getCustomCommands();
        $snippet = $nightbot->commandSnippet($user);

        $installedCommands = collect($commands['commands'] ?? [])
            ->filter(fn (array $command) => str_contains($command['message'], NightbotBotProvider::COMMAND_URL));

        $commandStatus = match (true) {
            $installedCommands->isEmpty() => 'missing',
            $installedCommands->every(fn (array $command) => $command['message'] === $snippet) => 'current',
            default => 'outdated',
        };

        return view('dashboard', [
            'commandStatus' => $commandStatus,
            'commandNames' => $installedCommands->pluck('name')->all(),
            'commandSnippet' => $snippet,
            'defaultCommandName' => NightbotBotProvider::DEFAULT_COMMAND_NAME,
            'settings' => $user->settings,
            'enabledProviders' => config('xgpt.enabled_providers'),
            'defaultDailyLimit' => (int) config('xgpt.own_key.daily_request_limit'),
            'maxDailyLimit' => (int) config('xgpt.own_key.max_daily_request_limit'),
            'history' => config('xgpt.history'),
        ]);
    }

    public function save(Request $request, NightbotBotProvider $nightbot, #[CurrentUser] User $user)
    {
        if ($request->has('install_command')) {
            $token = session('nightbot_api_token');

            if (! $token) {
                return redirect()->route('logout');
            }

            $validated = $request->validate([
                'command_name' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^\S+$/'],
            ], [
                'command_name.regex' => 'The command name can\'t contain spaces.',
            ]);

            try {
                $nightbot->install($user, $token, $validated['command_name'] ?? NightbotBotProvider::DEFAULT_COMMAND_NAME);
            } catch (CommandNameTakenException) {
                return back()->withInput()->withErrors([
                    'command_name' => 'You already have a different command with that name. Pick another name.',
                ]);
            }

            Session::flash('message', 'Command installed/updated!');
            Session::flash('alert-class', 'callout-block-success');

            return redirect()->route('dashboard');
        }

        $providers = collect(config('xgpt.enabled_providers'))->pluck('key');

        $validated = $request->validate([
            'instructions' => ['nullable', 'string', 'max:2000'],
            'ai_provider' => ['nullable', 'string', 'in:'.$providers->implode(',')],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'daily_request_limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('xgpt.own_key.max_daily_request_limit')],
            'history_max_messages' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('xgpt.history.own_key_max_messages')],
            'history_window_minutes' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('xgpt.history.own_key_max_window_minutes')],
        ]);

        $user->settings()->updateOrCreate(['user_id' => $user->id], [
            'instructions' => $validated['instructions'] ?? null,
            'ai_provider' => $validated['ai_provider'] ?? null,
            // Only overwrite the stored key when a new one is actually submitted — an empty
            // field means "keep what's saved", not "clear it".
            ...($request->filled('ai_api_key') ? ['ai_api_key' => $validated['ai_api_key']] : []),
            'daily_request_limit' => $validated['daily_request_limit'] ?? null,
            'use_history' => $request->boolean('use_history'),
            'history_max_messages' => $validated['history_max_messages'] ?? null,
            'history_window_minutes' => $validated['history_window_minutes'] ?? null,
            'mention_user' => $request->boolean('mention_user'),
            'show_sponsor_heart' => $request->boolean('show_sponsor_heart'),
        ]);

        Session::flash('message', 'Successfully saved your settings!');
        Session::flash('alert-class', 'callout-block-success');

        return redirect()->route('dashboard');
    }
}
