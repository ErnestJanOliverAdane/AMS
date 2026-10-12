@extends('layouts.app')

@section('title', 'My dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hello, {{ $user->name }}</h1>
            <p>This is your account area.</p>
        </div>
        <a class="btn" href="{{ route('profile.edit') }}">Edit profile</a>
    </div>

    <div class="grid2">
        <div class="card">
            <h2 style="font-size:1.1rem;margin-bottom:12px">Next appointment</h2>
            @if ($next)
                <p><strong>{{ $next->service->name }}</strong> · <x-status :value="$next->status" /></p>
                <p class="muted" style="margin:6px 0 14px">{{ $next->dateLabel() }}, {{ $next->timeLabel() }}</p>
                <a class="btn small" href="{{ route('client.appointments.show', $next) }}">Details</a>
            @else
                <p class="muted" style="margin-bottom:14px">You have nothing booked.</p>
                <a class="btn primary small" href="{{ route('client.appointments.create') }}">Book now</a>
            @endif
        </div>
        <div class="card">
            <h2 style="font-size:1.1rem;margin-bottom:12px">Unread notifications</h2>
            @forelse ($unread as $item)
                <div class="notice-row unread"><div><strong><a href="{{ route('notifications.open', $item->id) }}">{{ $item->data['title'] ?? 'Notification' }}</a></strong><p class="muted" style="font-size:13px">{{ $item->data['body'] ?? '' }}</p></div></div>
            @empty
                <p class="muted">You are all caught up.</p>
            @endforelse
        </div>
    </div>

    <div class="card form-card">
        <h2 style="font-size:1.1rem;margin-bottom:16px">Your account</h2>
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:10px 22px;margin:0">
            <dt class="muted">Name</dt><dd style="margin:0">{{ $user->name }}</dd>
            <dt class="muted">Email</dt><dd style="margin:0;overflow-wrap:anywhere">{{ $user->email }}</dd>
            <dt class="muted">Role</dt><dd style="margin:0"><span class="badge {{ $user->role->value }}">{{ $user->role->label() }}</span></dd>
            <dt class="muted">Member since</dt><dd style="margin:0">{{ $user->created_at?->format('F j, Y') }}</dd>
        </dl>
    </div>

    <div class="vault-bottom">@include('partials.vault', ['vault' => $vault])</div>
@endsection
