<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Welcome') · {{ config('portal.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-bg.css') }}">
    @include('partials.pwa')
</head>
<body>
@include('partials.animated-bg')
<main class="guest-wrap">
    <a class="guest-brand" href="{{ route('home') }}">
        <img src="{{ asset('images/logo.png') }}" alt="" width="56" height="56">
        <span>{{ config('portal.name') }}</span>
    </a>

    @if (session('status'))
        <div class="flash ok" role="status">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
<script src="{{ asset('js/portal-bg.js') }}" defer></script>
</body>
</html>
