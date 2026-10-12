@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="page-head">
        <div>
            <h1>Notifications</h1>
            <p>Reminders and updates about appointments.</p>
        </div>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn" type="submit">Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="card">
        @forelse ($notifications as $item)
            <div @class(['notice-row', 'unread' => $item->read_at === null])>
                <div>
                    <strong><a href="{{ route('notifications.open', $item->id) }}">{{ $item->data['title'] ?? 'Notification' }}</a></strong>
                    <p>{{ $item->data['body'] ?? '' }}</p>
                </div>
                <small class="muted" style="white-space:nowrap">{{ $item->created_at?->timezone(config('appointments.timezone'))->format('M j, g:i A') }}</small>
            </div>
        @empty
            <p class="empty">You have no notifications.</p>
        @endforelse
    </div>
    {{ $notifications->links('pagination::bootstrap-4') }}
@endsection
