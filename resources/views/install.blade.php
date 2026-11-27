@extends('layout')

@section('title', 'XGPT - Install')

@section('content')
    <h1>Install</h1>
    <p>Paste the command for your bot in your chat, and you're done.</p>
    @include('partials.commands')

    <h2>Settings</h2>
    <p>Sign in to give the bot your own instructions: a personality, a language, or info about your stream.</p>
    @auth
        <a href="/settings" class="btn">Open settings</a>
    @else
        <a href="/login" class="btn">Sign in</a>
    @endauth
@endsection
