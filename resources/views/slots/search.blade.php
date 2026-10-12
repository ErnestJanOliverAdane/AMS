@extends(auth()->check() ? 'layouts.app' : 'layouts.public')

@section('title', 'Available slots')

@section('content')
    <div class="page-head">
        <div>
            <h1>Available slots</h1>
            <p>See open dates and times before you book.</p>
        </div>
    </div>

    <div class="card stack">
        <form method="GET" action="{{ route('slots.search') }}" class="filters">
            <label>Service
                <select name="service_id" onchange="this.form.submit()">
                    <option value="">Select a service…</option>
                    @foreach ($services as $option)
                        <option value="{{ $option->id }}" @selected($service?->id === $option->id)>{{ $option->name }} ({{ $option->durationLabel() }})</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="btn" type="submit">Show days</button></noscript>
        </form>

        @if ($service)
            @include('partials.day-strip', ['days' => $days, 'date' => $date, 'service' => $service])

            @if ($date !== '')
                @include('partials.slot-grid', ['slots' => $slots, 'selectable' => false])

                @auth
                    @if (auth()->user()->role === \App\Enums\Role::User)
                        <div><a class="btn primary" href="{{ route('client.appointments.create', ['service_id' => $service->id, 'date' => $date]) }}">Book this day</a></div>
                    @endif
                @else
                    <div><a class="btn primary" href="{{ route('login') }}">Sign in to book</a></div>
                @endauth
            @else
                <p class="muted">Pick a day to see its times.</p>
            @endif
        @endif
    </div>
@endsection
