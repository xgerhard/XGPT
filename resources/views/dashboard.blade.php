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
            <p><small><strong>Keep this private.</strong> The part after <code>token=</code> is your personal code. Don't share it or paste it into your chat: if you add your own API key below, anyone with this code can use it.</small></p>
        @endif
    </fieldset>

    <form method="POST" action="{{ route('dashboard.save') }}">
        @csrf

        <fieldset>
            <legend>Support XGPT</legend>

            <p>Every time someone uses this command in your chat, it costs money: each reply is a paid request to an AI service, like ChatGPT. The free tier is paid for by a shared pool that the community funds through <a href="https://github.com/sponsors/xgerhard" target="_blank" rel="noopener">GitHub Sponsors</a>. If the pool runs out, the bot replies with a short notice until it resets at the start of the next month.</p>
            <p>Rather pay for your channel's usage yourself? Pick a provider and add your own API key below. Your chat then keeps working even when the shared pool runs out.</p>
            <p>As a thank-you, GitHub sponsors and channels using their own API key get a ❤️ next to their name whenever they use the command, in any channel.</p>

            <label>
                <input type="checkbox" name="show_sponsor_heart" value="1" @checked($settings?->show_sponsor_heart ?? true)>
                Show the ❤️ next to my name
            </label>

            <p><small>For reference: with the default OpenAI model ({{ $freeTierModel }}), 1,000 replies cost well under $0.10. Even a busy channel with 300 replies a day usually spends less than $1 a month. Other models can cost more, and remembering more messages makes each reply a bit more expensive.</small></p>
        </fieldset>

        <fieldset>
            <legend>AI provider</legend>

            <label for="ai_provider">Provider</label>
            <select name="ai_provider" id="ai_provider">
                <option value="">Free (shared, paid by sponsors)</option>
                @foreach ($enabledProviders as $provider)
                    <option value="{{ $provider['key'] }}" data-key-help-url="{{ $provider['key_help_url'] }}" @selected($settings?->ai_provider === $provider['key'])>
                        {{ $provider['label'] }} – use my own API key
                    </option>
                @endforeach
            </select>

            <div id="ai_api_key_field" class="own-key-only" @if (! $settings?->ai_provider) hidden @endif>
                <label for="ai_api_key">API key</label>
                <input type="password" name="ai_api_key" id="ai_api_key" placeholder="{{ $settings?->ai_provider ? 'Leave blank to keep your saved key' : '' }}">
                <p><small>A personal key from your AI provider that lets XGPT use your account. <a id="ai_api_key_help" href="{{ collect($enabledProviders)->firstWhere('key', $settings?->ai_provider)['key_help_url'] ?? '#' }}" target="_blank" rel="noopener">Get your API key →</a></small></p>
                <div class="notice">
                    <strong>⚠️ Important: set a spending limit with your AI provider.</strong>
                    It's the only limit that's fully guaranteed. We've built safeguards, but we can't fully stop someone from spamming your command, whether through a bot or with a leaked command link. By adding your own API key, you accept the risk of unexpected usage and costs.
                </div>

                <label for="daily_request_limit">Daily reply limit</label>
                <input type="number" name="daily_request_limit" id="daily_request_limit" min="1" max="{{ $maxDailyLimit }}" placeholder="{{ $defaultDailyLimit }}" value="{{ old('daily_request_limit', $settings?->daily_request_limit) }}">
                <p><small>The maximum number of times per day the bot replies to your command. It resets once a day. Leave empty for the recommended {{ number_format($defaultDailyLimit) }}; the most you can set is {{ number_format($maxDailyLimit) }}.</small></p>
            </div>
        </fieldset>

        <fieldset>
            <legend>Behavior</legend>

            <label for="instructions">Personality (optional)</label>
            <textarea name="instructions" id="instructions" rows="3" placeholder="e.g. Answer like a sarcastic pirate.">{{ old('instructions', $settings?->instructions) }}</textarea>
            <p><small>Replies are automatically kept short enough for chat. Use this to add extra flavor, tone or context.</small></p>

            <label>
                <input type="checkbox" name="mention_user" value="1" @checked($settings?->mention_user ?? true)>
                Start every reply with an @mention of the chatter who used the command
            </label>

            <label>
                <input type="checkbox" name="use_history" value="1" @checked($settings?->use_history ?? true)>
                Remember the conversation
            </label>
            <p><small>When enabled, follow-up questions work. When disabled, every message is answered on its own: nothing is stored, and each reply is cheaper.</small></p>

            <div class="own-key-only" @if (! $settings?->ai_provider) hidden @endif>
                <p><strong>Extra conversation settings with your own API key</strong><br>
                <small>Leave empty for the defaults ({{ $history['max_messages'] }} messages, {{ $history['window_minutes'] }} minutes). The maximum is {{ $history['own_key_max_messages'] }} messages and {{ $history['own_key_max_window_minutes'] }} minutes. More history means better follow-ups, but every reply costs a bit more.</small></p>

                <label for="history_max_messages">Messages to remember</label>
                <input type="number" name="history_max_messages" id="history_max_messages" min="2" step="2" max="{{ $history['own_key_max_messages'] }}" placeholder="{{ $history['max_messages'] }}" value="{{ old('history_max_messages', $settings?->history_max_messages) }}">
                <p><small>The most recent messages the bot remembers, at most this many, even if more were sent within the time below. A question and the bot's reply count as 2, so this is always an even number.</small></p>

                <label for="history_window_minutes">Minutes to look back</label>
                <input type="number" name="history_window_minutes" id="history_window_minutes" min="1" max="{{ $history['own_key_max_window_minutes'] }}" placeholder="{{ $history['window_minutes'] }}" value="{{ old('history_window_minutes', $settings?->history_window_minutes) }}">
                <p><small>Only messages sent within this many minutes are remembered.</small></p>
            </div>
        </fieldset>

        <button type="submit" name="save_settings" value="1">Save settings</button>
    </form>

    <script>
        document.getElementById('ai_provider').addEventListener('change', function () {
            const option = this.selectedOptions[0];

            document.querySelectorAll('.own-key-only').forEach(el => el.hidden = this.value === '');
            document.getElementById('ai_api_key_help').href = option.dataset.keyHelpUrl || '#';
        });
    </script>
@endsection
