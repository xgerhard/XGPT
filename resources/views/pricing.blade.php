@extends('layout')

@section('title', 'XGPT - Pricing')

@section('content')
    <h1>Pricing</h1>
    <p class="lead">Free for every channel. No ads, no paywall.</p>
    <p>Running it isn't free though. Every answer is a paid request to OpenAI, and there's the server it runs on. A single answer costs very little, but with thousands of questions a day across all channels, it adds up. XGPT relies on sponsors to keep running.</p>

    <div class="callout">
        <b>$1 a month makes a huge difference</b>
        <p>It covers about 35,000 answers in chat.</p>
        <a href="https://github.com/sponsors/xgerhard" target="_blank" class="btn btn-primary">Sponsor $1 a month</a>
    </div>

    <h2>When the money runs out</h2>
    <p>There's a monthly budget. Once it's used up, XGPT stops answering in <b>every</b> channel until the next month. Viewers then get this instead:</p>
    <div class="chat">
        <p><span class="viewer">viewer</span>!ai what should I name my cat?</p>
        <p><span class="bot">Bot</span>Error: XGPT has reached its monthly usage limit. Support this project: https://github.com/sponsors/xgerhard ❤️</p>
    </div>

    <h2>Sponsors</h2>
    <p>Sponsors keep XGPT running for everyone. As a thank you, every answer to a sponsor starts with a ❤️:</p>
    <div class="chat">
        <p><span class="viewer">sponsor</span>!ai thanks for keeping this running!</p>
        <p><span class="bot">Bot</span>[❤️] @sponsor Thank you for supporting XGPT!</p>
    </div>
    <p>Don't want the heart? Turn it off in your <a href="/settings">settings</a>.</p>

    <h2>Other ways to help</h2>
    <ul>
        <li>Tell other streamers about XGPT.</li>
        <li>Star the project or contribute code on <a href="https://github.com/xgerhard/XGPT" target="_blank">GitHub</a>.</li>
        <li>Found a bug or have an idea? Let me know via <a href="/contact">Contact</a>.</li>
    </ul>
@endsection
