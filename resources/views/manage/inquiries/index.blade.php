@extends('layouts.app')

@section('title', 'Guest messages')

@section('content')
    <div class="page-head">
        <div>
            <h1>Guest messages</h1>
            <p>Messages sent from the public Get Started form. Reply by phone or email, then mark them handled.</p>
        </div>
    </div>

    <div class="tabs">
        <a href="{{ route('manage.inquiries.index') }}" @class(['on' => ! $handled])>New</a>
        <a href="{{ route('manage.inquiries.index', ['status' => 'handled']) }}" @class(['on' => $handled])>Handled</a>
    </div>

    <div class="stack">
        @forelse ($inquiries as $item)
            <div class="card">
                <div class="notice-row" style="padding-top:0">
                    <div>
                        <strong>{{ $item->name }}</strong>
                        <p class="muted" style="font-size:13px">
                            <span class="mono">{{ $item->phone }}</span>
                            @if ($item->email) · <span class="mono">{{ $item->email }}</span>@endif
                            · {{ $item->created_at?->timezone(config('appointments.timezone'))->format('M j, Y g:i A') }}
                        </p>
                        @if ($item->services)<p class="muted" style="font-size:13px;margin-top:4px">Interested in: {{ $item->services }}</p>@endif
                        <p style="margin-top:10px;white-space:pre-line">{{ $item->message }}</p>
                    </div>
                    @unless ($handled)
                        <form method="POST" action="{{ route('manage.inquiries.handle', $item) }}">@csrf<button class="btn small primary" type="submit">Mark handled</button></form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="card"><p class="empty">Nothing here.</p></div>
        @endforelse
    </div>
    {{ $inquiries->links('pagination::bootstrap-4') }}
@endsection
