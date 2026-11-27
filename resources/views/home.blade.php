@extends('layout')

@section('title', 'XGPT - An AI chatbot for your stream chat, with Nightbot or Fossabot')

@section('content')
    <h1>XGPT</h1>
    <p class="lead">An AI chatbot for your stream chat. Viewers ask a question, the bot answers right in chat.</p>
    <p>Works with Nightbot and Fossabot, on Twitch, Kick and YouTube. Free, powered by <a href="https://openai.com" target="_blank">OpenAI</a>.</p>
    <a href="/install" class="btn btn-primary">Install</a>

    <h2>How it works</h2>
    <ol class="steps">
        <li><b>Add the command</b>Paste one line in your chat to add <code>!ai</code> to Nightbot or Fossabot.</li>
        <li><b>Viewers ask</b>Anyone types <code>!ai</code> followed by a question.</li>
        <li><b>The bot answers</b>The answer shows up in chat, mentioning the viewer.</li>
    </ol>
    <div class="chat">
        <p><span class="viewer">viewer</span>!ai Tell me about the origin of Twitch</p>
        <p><span class="bot">Bot</span>@viewer Twitch began in 2007 as Justin.tv, a live-streaming site founded by Justin Kan and Emmett Shear. Its gaming section grew so popular that it spun off as Twitch in 2011; Amazon acquired it in 2014.</p>
    </div>

    <h2 id="conversations">Continue a conversation</h2>
    <p>Every <code>!ai</code> starts a new conversation. Put a <b>+</b> at the start to continue your own last one:</p>
    <div class="chat">
        <p><span class="viewer">viewer</span>!ai who sang Never Gonna Give You Up?</p>
        <p><span class="bot">Bot</span>@viewer Rick Astley, released in 1987.</p>
        <p><span class="viewer">viewer</span>!ai <b>+</b> what else did he make?</p>
        <p><span class="bot">Bot</span>@viewer Hits like Together Forever and Whenever You Need Somebody.</p>
    </div>
    <p>Want others to join in? Turn on <b>Conversation ID</b> in your settings. Each answer then ends with a code like <code>#abc</code>, and anyone can continue it with <code>!ai #abc tell me more</code>.</p>
@endsection
