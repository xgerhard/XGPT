@extends('layout')

@section('title', 'XGPT - Settings')

@section('content')
    @if(Session::has('message'))
        <div class="alert {{ Session::get('alert-class', 'alert-success') }}">{{ Session::get('message') }}</div>
    @endif

    <h1>Settings</h1>
    <p>Hiya {{ auth()->user()->display_name }}! These settings only affect your channel.</p>

    <form action="" method="POST">
        @csrf
        <div class="field">
            <label for="start_instructions">AI instructions</label>
            <textarea id="start_instructions" name="start_instructions" rows="4" maxlength="500" placeholder="You are a helpful assistant for my stream chat.">{{ auth()->user()->settings?->start_instructions }}</textarea>
            <p class="hint">Given to the AI before every answer: a personality, a language, or info about your stream. Max 500 characters.</p>
            <details>
                <summary>Show examples</summary>
                <ul>
                    <li>You are an assistant for my stream chat. Be snappy and roast the viewer.</li>
                    <li>You are an assistant for my stream chat. Reply with a Texan accent.</li>
                    <li>You are an assistant for my stream chat. UwU'fy your replies and end with emojis.</li>
                    <li>You are an assistant for the channel xgerhard. Reply in Dutch.</li>
                </ul>
            </details>
        </div>

        <label class="check">
            <input type="checkbox" name="mention_user" @checked(auth()->user()->settings?->mention_user)>
            <span>Mention user<small>Start each answer with the viewer's name. Turn off for a few extra characters.</small></span>
        </label>
        <label class="check">
            <input type="checkbox" name="show_conversation_id" @checked(auth()->user()->settings?->show_conversation_id)>
            <span>Conversation ID<small>End each answer with a code like #abc, so anyone can continue that conversation. <a href="/#conversations">Examples</a></small></span>
        </label>
        <label class="check">
            <input type="checkbox" name="show_sponsor_heart" @checked(auth()->user()->settings?->show_sponsor_heart)>
            <span>Sponsor heart<small>Show a ❤️ in front of your name when you're a <a href="/pricing">sponsor</a>.</small></span>
        </label>

        <div class="actions">
            <button type="submit" name="save_settings" class="btn btn-primary">Save</button>
        </div>
    </form>
@endsection
