@extends('layouts.app')

@section('title', 'Support')

@section('content')
    <div class="page-head">
        <div>
            <h1>Support requests</h1>
            <p>Special appointment considerations asked by clients.</p>
        </div>
    </div>

    <div class="tabs">
        <a href="{{ route('manage.support.index') }}" @class(['on' => ! $closed])>Open</a>
        <a href="{{ route('manage.support.index', ['status' => 'closed']) }}" @class(['on' => $closed])>Answered</a>
    </div>

    <div class="stack">
        @forelse ($requests as $item)
            <div class="card">
                <div class="notice-row" style="padding-top:0">
                    <div>
                        <strong>{{ $item->subject }}</strong>
                        <p class="muted" style="font-size:13px">{{ $item->user->name }} · {{ $item->created_at?->format('M j, Y g:i A') }}
                            @if ($item->appointment) · <a href="{{ route('manage.appointments.show', $item->appointment) }}">{{ $item->appointment->reference }}</a>@endif
                        </p>
                        <p style="margin-top:8px">{{ $item->message }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('manage.support.reply', $item) }}" class="inline-form" style="margin-top:14px">
                    @csrf
                    <textarea name="admin_reply" maxlength="2000" required placeholder="Your reply">{{ $item->admin_reply }}</textarea>
                    <div><button class="btn primary small" type="submit">{{ $item->admin_reply ? 'Update reply' : 'Send reply' }}</button></div>
                </form>
            </div>
        @empty
            <div class="card"><p class="empty">Nothing here.</p></div>
        @endforelse
    </div>
    {{ $requests->links('pagination::bootstrap-4') }}
@endsection
