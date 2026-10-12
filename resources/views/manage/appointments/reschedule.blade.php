@extends('layouts.app')

@section('title', 'Reschedule')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reschedule {{ $appointment->reference }}</h1>
            <p>{{ $appointment->user->name }} · {{ $service->name }} · now {{ $appointment->dateLabel() }}, {{ $appointment->timeLabel() }}. The client is notified.</p>
        </div>
        <a class="btn" href="{{ route('manage.appointments.show', $appointment) }}">Back</a>
    </div>

    <div class="card stack" style="max-width:860px">
        @include('partials.day-strip', ['days' => $days, 'date' => $date, 'service' => null])

        @if ($date !== '')
            <form method="POST" action="{{ route('manage.appointments.reschedule.update', $appointment) }}" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="date" value="{{ $date }}">
                @include('partials.slot-grid', ['slots' => $slots, 'selectable' => true])
                @error('start_time') <span class="err" style="color:var(--danger)">{{ $message }}</span> @enderror
                <div><button class="btn primary" type="submit">Move appointment</button></div>
            </form>
        @else
            <p class="muted">Pick a day to see its times.</p>
        @endif
    </div>
@endsection
