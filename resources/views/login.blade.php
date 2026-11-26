<!DOCTYPE html>
<html lang="en">
<head>
    <title>XGPT - Sign in</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link id="theme-style" rel="stylesheet" href="/assets/css/theme.css">
    <style>
        .btn-platform { color: #fff; border: 0; }
        .btn-platform:hover, .btn-platform:focus { color: #fff; filter: brightness(1.1); }
        .btn-nightbot { background: #364fc7; }
        .btn-twitch { background: #9146ff; }
        .btn-kick, .btn-kick:hover, .btn-kick:focus { background: #53fc18; color: #000; }
    </style>
</head>
<body>
    <main class="container text-center py-5" style="max-width: 360px">
        <div class="site-logo mb-2"><a class="navbar-brand" href="/"><span class="logo-text">X<span class="text-alt">GPT</span></span></a></div>
        <p class="mb-4">Sign in with the platform of your channel.</p>
        <div class="d-grid gap-3">
            <a href="/login/nightbot" class="btn btn-platform btn-nightbot">Sign in with Nightbot</a>
            <a href="/login/twitch" class="btn btn-platform btn-twitch">Sign in with Twitch</a>
            <a href="/login/kick" class="btn btn-platform btn-kick">Sign in with Kick</a>
        </div>
        <p class="mt-4 small">Only with Nightbot can we install the command for you.</p>
    </main>
</body>
</html>
