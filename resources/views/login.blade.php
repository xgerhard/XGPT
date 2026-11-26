<!DOCTYPE html>
<html lang="en">
<head>
    <title>XGPT - Sign in</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link id="theme-style" rel="stylesheet" href="/assets/css/theme.css">
    <style>
        .btn-platform, .btn-platform:hover, .btn-platform:focus { color: #fff; }
        .btn-nightbot { background: #364fc7; }
        .btn-nightbot:hover, .btn-nightbot:focus { background: #2b3f9f; }
        .btn-twitch { background: #9146ff; }
        .btn-twitch:hover, .btn-twitch:focus { background: #772ce8; }
        .btn-kick, .btn-kick:hover, .btn-kick:focus { color: #000; }
        .btn-kick { background: #53fc18; }
        .btn-kick:hover, .btn-kick:focus { background: #45d614; }
        .or { display: flex; align-items: center; gap: 1rem; font-size: .875rem; }
        .or::before, .or::after { content: ""; flex: 1; border-top: 1px solid #e7e9ed; }
    </style>
</head>
<body>
    <main class="container text-center py-5" style="max-width: 360px">
        <div class="site-logo mb-4"><a class="navbar-brand" href="/"><span class="logo-text">X<span class="text-alt">GPT</span></span></a></div>
        <div class="d-grid gap-3">
            <a href="/login/nightbot" class="btn btn-platform btn-nightbot">Sign in with Nightbot</a>
            <div class="or">or</div>
            <a href="/login/twitch" class="btn btn-platform btn-twitch">Sign in with Twitch</a>
            <a href="/login/kick" class="btn btn-platform btn-kick">Sign in with Kick</a>
        </div>
    </main>
</body>
</html>
