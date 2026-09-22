@extends('layouts.app')

@section('title', 'Dashboard — XGPT')

@section('content')
    @php $settings = auth()->user()->settings; @endphp

    <h1>Dashboard</h1>

    <fieldset>
        <legend>Nightbot command</legend>

        @if (empty($commands))
            <p>Not installed yet.</p>
        @elseif ($commandNeedsUpdate)
            <p>Installed, but out of date — click below to refresh it.</p>
        @else
            <p>Installed and up to date.</p>
        @endif

        <form method="POST" action="{{ route('dashboard.save') }}">
            @csrf
            <button type="submit" name="install_command" value="1">Install / update command</button>
        </form>

        <p>Or paste this manually as a Nightbot custom command:</p>
        <pre>{{ $commandSnippet }}</pre>
    </fieldset>

    <form method="POST" action="{{ route('dashboard.save') }}">
        @csrf

        <fieldset>
            <legend>AI provider</legend>

            <label for="ai_provider">Provider</label>
            <select name="ai_provider" id="ai_provider" onchange="document.getElementById('ai_api_key_field').hidden = (this.value === '' || this.selectedOptions[0].dataset.freeTier === '1')">
                <option value="">Free tier (OpenAI, shared usage pool)</option>
                @foreach ($enabledProviders as $provider)
                    <option value="{{ $provider['key'] }}" data-free-tier="0" @selected($settings?->ai_provider === $provider['key'])>
                        {{ $provider['label'] }} (your own key)
                    </option>
                @endforeach
            </select>

            <div id="ai_api_key_field" @if (! $settings?->ai_provider) hidden @endif>
                <label for="ai_api_key">API key</label>
                <input type="password" name="ai_api_key" id="ai_api_key" placeholder="{{ $settings?->ai_provider ? 'Leave blank to keep your saved key' : '' }}">
            </div>
        </fieldset>

        <fieldset>
            <legend>Behavior</legend>

            <label for="instructions">Instructions</label>
            <textarea name="instructions" id="instructions" rows="3">{{ old('instructions', $settings?->instructions) }}</textarea>

            <label>
                <input type="checkbox" name="mention_user" value="1" @checked($settings?->mention_user ?? true)>
                Mention the chatter's name in replies
            </label>
            <label>
                <input type="checkbox" name="show_sponsor_heart" value="1" @checked($settings?->show_sponsor_heart ?? true)>
                Show a &hearts; next to my name for other channels I chat in
            </label>
        </fieldset>

        <button type="submit" name="save_settings" value="1">Save settings</button>
    </form>

    <p>
        Every reply is part of one rolling conversation shared by your whole chat — the last
        {{ config('xgpt.history.window_minutes') }} minutes (up to {{ config('xgpt.history.max_messages') }} messages),
        no hashtags needed.
    </p>
@endsection
