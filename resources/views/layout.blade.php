<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <meta name="description" content="XGPT - An AI chatbot for your stream chat, with Nightbot or Fossabot">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    @php
        $nav = [
            ['/', 'Home', '<path d="M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>'],
            ['install', 'Install', '<path d="m4 17 6-6-6-6M12 19h8"/>'],
            ['pricing', 'Pricing', '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>'],
            ['contact', 'Contact', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'],
        ];
        $nav = array_merge($nav, auth()->check() ? [
            ['settings', 'Settings', '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>'],
            ['logout', 'Logout', '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>'],
        ] : [
            ['login', 'Sign in', '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>'],
        ]);
    @endphp
    <aside class="sidebar">
        <a class="logo" href="/">XGPT</a>
        <nav class="nav">
            @foreach($nav as [$path, $label, $icon])
                <a href="/{{ ltrim($path, '/') }}" @class(['active' => request()->is($path)])>
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg>{{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>
    <main>
        <div class="content">
            @yield('content')
        </div>
    </main>
</body>
</html>
