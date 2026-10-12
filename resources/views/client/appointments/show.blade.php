@extends('layouts.app')

@section('title', $appointment->reference)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $appointment->service->name }}</h1>
            <p><span class="mono">{{ $appointment->reference }}</span> · <x-status :value="$appointment->status" /></p>
        </div>
        <a class="btn" href="{{ route('client.appointments.index') }}">Back</a>
    </div>

    <div class="grid2">
        <div class="card">
            <dl class="kv">
                <dt>Date</dt><dd>{{ $appointment->dateLabel() }}</dd>
                <dt>Time</dt><dd>{{ $appointment->timeLabel() }}</dd>
                <dt>Reason</dt><dd>{{ $appointment->reason ?: '—' }}</dd>
                @if ($appointment->admin_note)<dt>Note from us</dt><dd>{{ $appointment->admin_note }}</dd>@endif
                <dt>Changes made</dt><dd>{{ $appointment->reschedule_count }} of {{ config('appointments.max_reschedules') }}</dd>
            </dl>

            <div class="actions" style="margin-top:20px">
                @if ($hasSlip)<a class="btn primary" href="{{ route('client.appointments.slip', $appointment) }}">Confirmation slip</a>@endif
                @if ($canReschedule)<a class="btn" href="{{ route('client.appointments.reschedule', $appointment) }}">Reschedule</a>@endif
                @if ($canCancel)
                    <form method="POST" action="{{ route('client.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
                        @csrf
                        <button class="btn danger" type="submit">Cancel</button>
                    </form>
                @endif
            </div>
            @if ($appointment->status === \App\Enums\AppointmentStatus::Approved && $canCancel)
                <p class="muted" style="margin-top:12px;font-size:13px">Cancelling an approved appointment counts toward the cancellation limit.</p>
            @endif
        </div>

        @if ($appointment->status === \App\Enums\AppointmentStatus::Completed)
            <div class="card">
                <h2 style="font-size:1.1rem;margin-bottom:14px">Your feedback</h2>
                @if ($appointment->feedback)
                    <p class="stars">{{ str_repeat('★', $appointment->feedback->rating) }}{{ str_repeat('☆', 5 - $appointment->feedback->rating) }}</p>
                    <p style="margin-top:8px">{{ $appointment->feedback->comment ?: 'Thank you!' }}</p>
                @else
                    <form method="POST" action="{{ route('client.appointments.feedback', $appointment) }}" class="stack">
                        @csrf
                        <div class="rate" role="radiogroup" aria-label="Rating">
                            @foreach (range(1, 5) as $n)
                                <label><input type="radio" name="rating" value="{{ $n }}" @checked((int) old('rating') === $n) required> {{ $n }} ★</label>
                            @endforeach
                        </div>
                        @error('rating') <span class="err" style="color:var(--danger)">{{ $message }}</span> @enderror
                        <div class="field" style="margin:0">
                            <label for="comment">Comment (optional)</label>
                            <textarea id="comment" name="comment" maxlength="1000">{{ old('comment') }}</textarea>
                        </div>
                        <div><button class="btn primary" type="submit">Send feedback</button></div>
                    </form>
                @endif
            </div>
        @endif
    </div>
@endsection
