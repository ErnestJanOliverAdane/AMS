@extends('layouts.app')

@section('title', 'Book appointment')

@section('content')
    <div class="page-head">
        <div>
            <h1>Book an appointment</h1>
            <p>Choose a service, a day and a time. The team confirms every request.</p>
        </div>
    </div>

    @if ($restricted)
        <div class="flash bad">New requests are paused on your account because of repeated cancellations or no-shows. Please <a href="{{ route('client.support.index') }}">send a support request</a>.</div>
    @endif

    <div class="card stack" style="max-width:860px">
        <form method="GET" action="{{ route('client.appointments.create') }}" class="filters">
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
            @if ($service->description)<p class="muted">{{ $service->description }}</p>@endif

            <div>
                <h2 style="font-size:1rem;margin-bottom:10px">1. Pick a day</h2>
                @include('partials.day-strip', ['days' => $days, 'date' => $date, 'service' => $service])
            </div>

            @if ($date !== '')
                <form method="POST" action="{{ route('client.appointments.store') }}" class="stack">
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $service->id }}">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div>
                        <h2 style="font-size:1rem;margin-bottom:10px">2. Pick a time on {{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }}</h2>
                        @include('partials.slot-grid', ['slots' => $slots, 'selectable' => true])
                        @error('start_time') <span class="err" style="color:var(--danger)">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="reason">3. Reason (optional)</label>
                        <textarea id="reason" name="reason" maxlength="1000" placeholder="Tell us what you need help with">{{ old('reason') }}</textarea>
                        @error('reason') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div><button class="btn primary" type="submit" @disabled($restricted || $slots->where('remaining', '>', 0)->isEmpty())>Send request</button></div>
                </form>
            @endif
        @endif
    </div>
@endsection
