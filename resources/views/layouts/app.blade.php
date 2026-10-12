@use('App\Enums\Role')
@php
    $me = auth()->user();

    $unread = $me->unreadNotifications()->count();
    $isStaff = $me->role !== Role::User;
    $guestNew = $isStaff ? \App\Models\GuestInquiry::where('status', 'new')->count() : 0;
    $supportOpen = $isStaff ? \App\Models\SupportRequest::where('status', 'open')->count() : 0;
    $bellCount = $unread + $guestNew + $supportOpen;
    $recent = $me->notifications()->latest()->take(5)->get();
    $supportRoute = $isStaff ? 'manage.support.index' : 'client.support.index';
    $staffNav = [
        ['Appointments', 'manage.appointments.index', ['manage.appointments.*']],
        ['Calendar', 'manage.calendar', ['manage.calendar']],
        ['Reports', 'manage.reports', ['manage.reports', 'manage.export.*']],
        ['Schedule', 'manage.schedule.edit', ['manage.schedule.*']],
        ['Services', 'manage.services.index', ['manage.services.*']],
        ['Clients', 'manage.clients.index', ['manage.clients.*']],
    ];

    // [label, route name, active patterns]
    $nav = match ($me->role) {
        Role::SuperAdmin => [
            ['Dashboard', 'super-admin.dashboard', ['super-admin.dashboard']],
            ...$staffNav,
            ['Admins', 'super-admin.admins.index', ['super-admin.admins.*']],
            ['Users', 'manage.users.index', ['manage.users.*']],
        ],
        Role::Admin => [
            ['Dashboard', 'admin.dashboard', ['admin.dashboard']],
            ...$staffNav,
            ['Users', 'manage.users.index', ['manage.users.*']],
        ],
        Role::User => [
            ['Dashboard', 'user.dashboard', ['user.dashboard']],
            ['Book appointment', 'client.appointments.create', ['client.appointments.create']],
            ['My appointments', 'client.appointments.index', ['client.appointments.index', 'client.appointments.show', 'client.appointments.reschedule*', 'client.appointments.slip']],
            ['Find a slot', 'slots.search', ['slots.search']],
        ],
    };
    $nav[] = ['Site layouts', 'layouts.index', ['layouts.*']];
    $nav[] = ['Profile', 'profile.edit', ['profile.*']];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('portal.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-bg.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    @include('partials.pwa')
</head>
<body>
@include('partials.animated-bg')
<div class="app">
    <aside class="sidebar" id="side-menu">
        <div class="side-head">
            <a class="brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/logo.png') }}" alt="" width="40" height="40">
                <span>{{ config('portal.name') }}</span>
            </a>
            <button class="burger" type="button" aria-label="Close menu" aria-controls="side-menu" aria-expanded="true" data-menu-toggle><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path class="b1" d="M4 6h16"/><path class="b2" d="M4 12h16"/><path class="b3" d="M4 18h16"/></svg></button>
        </div>

        <nav class="nav" aria-label="Main">
            @foreach ($nav as $item)
                @php [$label, $route, $patterns] = $item; $count = $item[3] ?? 0; @endphp
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs(...$patterns)])>{{ $label }}@if ($count > 0)<span class="nav-count">{{ $count }}</span>@endif</a>
            @endforeach
        </nav>
    </aside>

    <button class="scrim" type="button" tabindex="-1" aria-hidden="true" data-menu-close></button>

    <main class="main">
        <div class="topbar no-print">
            <button class="burger burger-open" type="button" aria-label="Open menu" aria-controls="side-menu" aria-expanded="false" data-menu-toggle><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path class="b1" d="M4 6h16"/><path class="b2" d="M4 12h16"/><path class="b3" d="M4 18h16"/></svg></button>
            <span class="topbar-title">@yield('title', 'Dashboard')</span>
            <div class="topbar-right">
            <div class="usermenu bell" data-usermenu>
                <button class="bell-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="bell-dropdown" aria-label="Notifications{{ $bellCount ? ', '.$bellCount.' new' : '' }}" data-usermenu-toggle>
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 20a1.9 1.9 0 0 0 3.4 0"/></svg>
                    @if ($bellCount > 0)<span class="bell-count">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>@endif
                </button>
                <div class="usermenu-pop bell-pop" id="bell-dropdown" hidden>
                    <div class="bell-head">
                        <strong>Notifications</strong>
                        @if ($unread > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="link-btn">Mark all read</button></form>
                        @endif
                    </div>
                    <div class="bell-list">
                        @forelse ($recent as $item)
                            <a href="{{ route('notifications.open', $item->id) }}" @class(['bell-item', 'unread' => $item->read_at === null])>
                                <strong>{{ $item->data['title'] ?? 'Notification' }}</strong>
                                <span>{{ \Illuminate\Support\Str::limit($item->data['body'] ?? '', 80) }}</span>
                                <small>{{ $item->created_at?->timezone(config('appointments.timezone'))->format('M j, g:i A') }}</small>
                            </a>
                        @empty
                            <p class="bell-empty">You are all caught up.</p>
                        @endforelse
                    </div>
                    <div class="bell-links">
                        <a href="{{ route($supportRoute) }}"><span>Support</span>@if ($supportOpen > 0)<b>{{ $supportOpen }}</b>@endif</a>
                        @if ($isStaff)
                            <a href="{{ route('manage.inquiries.index') }}"><span>Guest messages</span>@if ($guestNew > 0)<b>{{ $guestNew }}</b>@endif</a>
                        @endif
                        <a href="{{ route('notifications.index') }}"><span>View all notifications</span></a>
                    </div>
                </div>
            </div>
            <div class="usermenu" data-usermenu>
                <button class="usermenu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="user-dropdown" data-usermenu-toggle>
                    <span class="avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name, 0, 1)) }}</span>
                    <span class="who"><strong>{{ $me->name }}</strong><small>{{ $me->email }}</small></span>
                    <svg class="chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="usermenu-pop" id="user-dropdown" role="menu" hidden>
                    <div class="usermenu-head">
                        <strong>{{ $me->name }}</strong>
                        <small>{{ $me->email }}</small>
                        <span class="badge {{ $me->role->value }}">{{ $me->role->label() }}</span>
                    </div>
                    <a role="menuitem" href="{{ route('profile.edit') }}">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                        Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button role="menuitem" type="submit">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H5v16h4"/><path d="m16 8 4 4-4 4"/><path d="M20 12H9"/></svg>
                            Sign out
                        </button>
                    </form>
                    <p class="lock-note">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <span>Personal details are encrypted</span>
                    </p>
                </div>
            </div>
            </div>
        </div>

        @if (session('status'))
            <div class="flash ok" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->has('appointment') || $errors->has('booking'))
            <div class="flash bad" role="alert">
                <ul>@foreach (array_merge($errors->get('appointment'), $errors->get('booking')) as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script src="{{ asset('js/portal-bg.js') }}" defer></script>
<script src="{{ asset('js/menu.js') }}" defer></script>
<script src="{{ asset('js/dashboard.js') }}" defer></script>
</body>
</html>
