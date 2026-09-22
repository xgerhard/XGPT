<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XGPT')</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 780px; margin: 0 auto; padding: 1.5rem; line-height: 1.5; }
        nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        nav a { text-decoration: none; font-weight: 600; }
        .flash { padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1.5rem; }
        .flash.callout-block-success { background: #d1fae5; color: #065f46; }
        .flash.callout-block-danger { background: #fee2e2; color: #991b1b; }
        code, pre { background: rgba(127,127,127,.15); border-radius: .35rem; padding: .15rem .4rem; }
        pre { padding: .75rem; overflow-x: auto; }
        fieldset { border: 1px solid rgba(127,127,127,.3); border-radius: .5rem; margin-bottom: 1.5rem; padding: 1rem; }
        label { display: block; font-weight: 600; margin-top: .75rem; }
        input[type=text], input[type=password], textarea, select { width: 100%; box-sizing: border-box; padding: .5rem; margin-top: .25rem; }
        button { padding: .5rem 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <nav>
        <a href="{{ url('/') }}">XGPT</a>
        <span>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                &middot;
                <a href="{{ route('logout') }}">Logout</a>
            @else
                <a href="{{ route('login') }}">Sign in with Nightbot</a>
            @endauth
        </span>
    </nav>

    @if (session('message'))
        <div class="flash {{ session('alert-class') }}">{{ session('message') }}</div>
    @endif

    @yield('content')
</body>
</html>
