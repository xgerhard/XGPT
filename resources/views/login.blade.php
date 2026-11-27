<!DOCTYPE html>
<html lang="en">
<head>
    <title>XGPT - Sign in</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .signin { max-width: 340px; margin: 12vh auto 0; padding: 0 1rem; text-align: center; }
        .signin .logo { padding: 0 0 .5rem; font-size: 2rem; }
        .signin .btn { display: block; width: 100%; margin-bottom: .75rem; border: 0; color: #fff; }
        .signin p { margin-bottom: 1.5rem; }
        .btn-nightbot { background: #364fc7; }
        .btn-nightbot:hover { background: #2b3f9f; }
        .btn-twitch { background: #9146ff; }
        .btn-twitch:hover { background: #772ce8; }
        .signin .btn-kick { background: #53fc18; color: #000; }
        .btn-kick:hover { background: #45d614; }
        .or { display: flex; align-items: center; gap: 1rem; margin: 0 0 .75rem; font-size: .875rem; color: var(--muted); }
        .or::before, .or::after { content: ""; flex: 1; border-top: 1px solid var(--border); }
    </style>
</head>
<body>
    <main class="signin">
        <a class="logo" href="/">XGPT</a>
        <p>Sign in to customize your AI chatbot.</p>
        <a href="/login/nightbot" class="btn btn-nightbot">Sign in with Nightbot</a>
        <div class="or">or</div>
        <a href="/login/twitch" class="btn btn-twitch">Sign in with Twitch</a>
        <a href="/login/kick" class="btn btn-kick">Sign in with Kick</a>
    </main>
</body>
</html>
