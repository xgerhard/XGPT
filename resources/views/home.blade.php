@extends('layouts.app')

@section('title', 'XGPT: AI chat for Nightbot')

@section('content')
    <h1>XGPT</h1>
    <p>
        Add an AI chatbot to your stream via <a href="https://nightbot.tv" target="_blank" rel="noopener">Nightbot</a>.
        Chatters ask a question, XGPT answers in chat, powered by your choice of AI provider.
    </p>

    <h2>Quick start</h2>
    <ol>
        <li><a href="{{ route('login') }}">Sign in with Nightbot</a> to get your personal setup link.</li>
        <li>From your dashboard, click <strong>Install / update command</strong>. This adds an
            <code>!ai</code> command to your Nightbot automatically.</li>
        <li>That's it. Chat can now talk to the bot. No API key required to start; XGPT covers a
            free daily usage pool on OpenAI. Add your own key later to raise your limits or pick a
            different provider.</li>
    </ol>

    <h2>How replies work</h2>
    <p>
        Each channel has one rolling conversation with the bot: the last {{ config('xgpt.history.window_minutes') }}
        minutes of chat with it (up to {{ config('xgpt.history.max_messages') }} messages), shared by everyone
        who talks to it, so the bot can follow a back-and-forth naturally.
    </p>

    <h2>Providers</h2>
    <ul>
        @foreach (config('xgpt.enabled_providers') as $provider)
            <li>
                <strong>{{ $provider['label'] }}</strong>
                @if ($provider['free_tier'])
                    : free tier available, or bring your own <a href="{{ $provider['key_help_url'] }}" target="_blank" rel="noopener">API key</a>
                @else
                    : bring your own <a href="{{ $provider['key_help_url'] }}" target="_blank" rel="noopener">API key</a>
                @endif
            </li>
        @endforeach
    </ul>

    <p><a href="{{ route('login') }}">Sign in with Nightbot to get started</a></p>
