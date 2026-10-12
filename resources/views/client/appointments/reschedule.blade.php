@extends('layouts.app')

@section('title', 'Reschedule')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reschedule</h1>
            <p>{{ $service->name }} · now {{ $appointment->dateLabel() }}, {{ $appointment->timeLabel() }}. The team will approve the new time again.</p>
        </div>
        <a class="btn" href="{{ route('client.appointments.show', $appointment) }}">Back</a>
    </div>

    <div class="card stack" style="max-width:860px">
        @include('partials.day-strip', ['days' => $days, 'date' => $date, 'service' => null])

        @if ($date !== '')
            <form method="POST" action="{{ route('client.appointments.reschedule.update', $appointment) }}" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="date" value="{{ $date }}">
                @include('partials.slot-grid', ['slots' => $slots, 'selectable' => true])
                @error('start_time') <span class="err" style="color:var(--danger)">{{ $message }}</span> @enderror
                <div><button class="btn primary" type="submit">Request this time</button></div>
            </form>
        @endif
    </div>
@endsection
