@extends('layouts.app')

@section('title', 'XGPT Dashboard')

@section('content')
    <h1>Dashboard</h1>

    <fieldset>
        <legend>Nightbot command</legend>

        @if ($commandStatus === 'current')
            <p>Your command <code>{{ implode(', ', $commandNames) }}</code> is installed and up to date.</p>
        @elseif ($commandStatus === 'outdated')
            <p>We found your command <code>{{ implode(', ', $commandNames) }}</code>, but it's out of date.</p>

            <form method="POST" action="{{ route('dashboard.save') }}">
                @csrf
                <button type="submit" name="install_command" value="1">Update command</button>
            </form>
        @else
            <p>No XGPT command found in your Nightbot commands yet.</p>

            <form method="POST" action="{{ route('dashboard.save') }}">
                @csrf
                <label for="command_name">Command name</label>
                <div class="inline-field">
                    <input type="text" name="command_name" id="command_name" required maxlength="50" value="{{ old('command_name', $defaultCommandName) }}">
                    <button type="submit" name="install_command" value="1">Install command</button>
                </div>
            </form>

            <p>Or paste this manually in the "Response" field when creating a new Nightbot command:</p>
            <div class="inline-field">
                <pre id="command_snippet">{{ $commandSnippet }}</pre>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('command_snippet').textContent).then(() => { this.textContent = 'Copied!'; })">Copy</button>
            </div>
            <p><small><strong>Keep this token private.</strong> Don't share it or paste it into your chat: if you use your own AI API key below, anyone with the token can use it.</small></p>
        @endif
    </fieldset>

    <form method="POST" action="{{ route('dashboard.save') }}">
        @csrf

        <fieldset>
            <legend>Support XGPT</legend>

            <p>Every time someone uses the command, it costs money: each reply is a paid request to an AI provider. The free tier runs on a shared pool funded by the community through <a href="https://github.com/sponsors/xgerhard" target="_blank" rel="noopener">GitHub Sponsors</a>.</p>
            <p>Rather pay for your own usage? Choose a provider and add your own API key below, and your channel won't be affected by the shared pool's limits.</p>
            <p>As a thank-you, GitHub sponsors and channels using their own API key get a ❤️ next to their name whenever they use the command, in any channel.</p>

            <label>
                <input type="checkbox" name="show_sponsor_heart" value="1" @checked($settings?->show_sponsor_heart ?? true)>
                Show the ❤️ next to my name
            </label>
        </fieldset>

        <fieldset>
            <legend>AI provider</legend>

            <label for="ai_provider">Provider</label>
            <select name="ai_provider" id="ai_provider" onchange="document.querySelectorAll('.own-key-only').forEach(el => el.hidden = (this.value === '' || this.selectedOptions[0].dataset.freeTier === '1'))">
                <option value="">Free tier (OpenAI, shared usage pool)</option>
                @foreach ($enabledProviders as $provider)
                    <option value="{{ $provider['key'] }}" data-free-tier="0" @selected($settings?->ai_provider === $provider['key'])>
                        {{ $provider['label'] }} (your own key)
                    </option>
                @endforeach
            </select>

            <div id="ai_api_key_field" class="own-key-only" @if (! $settings?->ai_provider) hidden @endif>
                <label for="ai_api_key">API key</label>
                <input type="password" name="ai_api_key" id="ai_api_key" placeholder="{{ $settings?->ai_provider ? 'Leave blank to keep your saved key' : '' }}">
                <p><small><strong>Set a spending limit with your AI provider.</strong> It's the only cap that's fully guaranteed. XGPT has its own safeguards, but chat commands can't be fully verified, and anyone who gets hold of your command URL can use your key. You are responsible for all usage on your key.</small></p>

                <label for="daily_request_limit">Daily reply limit</label>
                <input type="number" name="daily_request_limit" id="daily_request_limit" min="1" max="{{ $maxDailyLimit }}" placeholder="{{ $defaultDailyLimit }}" value="{{ old('daily_request_limit', $settings?->daily_request_limit) }}">
                <p><small>The most replies XGPT will generate with your key per day (resets at midnight UTC). Leave empty for the recommended {{ number_format($defaultDailyLimit) }}. The maximum is {{ number_format($maxDailyLimit) }}.</small></p>
            </div>
        </fieldset>

        <fieldset>
            <legend>Behavior</legend>

            <label for="instructions">Personality (optional)</label>
            <textarea name="instructions" id="instructions" rows="3" placeholder="e.g. Answer like a sarcastic pirate.">{{ old('instructions', $settings?->instructions) }}</textarea>
            <p><small>The bot already knows how to handle a multi-user chat and keep replies short enough to fit. This is just for extra flavor, tone, or context. It's added on top, not a replacement.</small></p>

            <label>
                <input type="checkbox" name="mention_user" value="1" @checked($settings?->mention_user ?? true)>
                Start every reply with an @mention of the chatter who used the command
            </label>

            <label>
                <input type="checkbox" name="use_history" value="1" @checked($settings?->use_history ?? true)>
                Remember the conversation
            </label>
            <p><small>Replies take the last few chat messages into account, so follow-up questions work. Turn this off to answer every message on its own: nothing is stored, and each reply is cheaper.</small></p>

            <div class="own-key-only" @if (! $settings?->ai_provider) hidden @endif>
                <label for="history_max_messages">Messages to remember</label>
                <input type="number" name="history_max_messages" id="history_max_messages" min="1" max="{{ $history['own_key_max_messages'] }}" placeholder="{{ $history['max_messages'] }}" value="{{ old('history_max_messages', $settings?->history_max_messages) }}">

                <label for="history_window_minutes">Minutes to look back</label>
                <input type="number" name="history_window_minutes" id="history_window_minutes" min="1" max="{{ $history['own_key_max_window_minutes'] }}" placeholder="{{ $history['window_minutes'] }}" value="{{ old('history_window_minutes', $settings?->history_window_minutes) }}">

                <p><small>Available with your own API key. Leave empty for the defaults ({{ $history['max_messages'] }} messages, {{ $history['window_minutes'] }} minutes); the maximum is {{ $history['own_key_max_messages'] }} messages and {{ $history['own_key_max_window_minutes'] }} minutes. More history means better follow-ups, but every reply costs more tokens.</small></p>
            </div>
        </fieldset>

        <button type="submit" name="save_settings" value="1">Save settings</button>
    </form>
@endsection
