<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Welcome') · {{ config('portal.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    @include('partials.pwa')
</head>
<body>
<main class="main" style="max-width:980px;margin:0 auto">
    <div class="page-head">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="" width="40" height="40">
            <span>{{ config('portal.name') }}</span>
        </a>
        <div class="actions">
            @auth
                <a class="btn" href="{{ route('dashboard') }}">My portal</a>
            @else
                <a class="btn" href="{{ route('login') }}">Sign in</a>
                @if (config('portal.allow_registration'))<a class="btn primary" href="{{ route('register') }}">Create account</a>@endif
            @endauth
        </div>
    </div>
    @yield('content')
</main>
</body>
</html>
