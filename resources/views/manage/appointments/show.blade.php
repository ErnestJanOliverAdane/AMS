@extends('layouts.app')

@section('title', $appointment->reference)

@section('content')
    @php $status = $appointment->status; @endphp
    <div class="page-head">
        <div>
            <h1>{{ $appointment->service->name }}</h1>
            <p><span class="mono">{{ $appointment->reference }}</span> · <x-status :value="$status" /></p>
        </div>
        <a class="btn" href="{{ route('manage.appointments.index') }}">Back</a>
    </div>

    <div class="grid2">
        <div class="card">
            <dl class="kv">
                <dt>Client</dt><dd>{{ $appointment->user->name }} · {{ $appointment->user->email }}</dd>
                <dt>Date</dt><dd>{{ $appointment->dateLabel() }}</dd>
                <dt>Time</dt><dd>{{ $appointment->timeLabel() }}</dd>
                <dt>Reason</dt><dd>{{ $appointment->reason ?: '—' }}</dd>
                <dt>Note</dt><dd>{{ $appointment->admin_note ?: '—' }}</dd>
                <dt>Reschedules</dt><dd>{{ $appointment->reschedule_count }}</dd>
                <dt>Client strikes</dt><dd>{{ $strikes }} / {{ config('appointments.restriction.threshold') }} @if ($appointment->user->isBookingRestricted())<span class="badge off">Restricted</span>@endif</dd>
                @if ($appointment->feedback)
                    <dt>Rating</dt><dd><span class="stars">{{ str_repeat('★', $appointment->feedback->rating) }}</span> {{ $appointment->feedback->comment }}</dd>
                @endif
            </dl>
        </div>

        <div class="card stack">
            <h2 style="font-size:1.1rem">Actions</h2>

            @if ($status === \App\Enums\AppointmentStatus::Pending)
                <div class="actions">
                    <form method="POST" action="{{ route('manage.appointments.approve', $appointment) }}">@csrf<button class="btn primary" type="submit">Approve</button></form>
                </div>
                <form method="POST" action="{{ route('manage.appointments.reject', $appointment) }}" class="inline-form">
                    @csrf
                    <textarea name="admin_note" maxlength="500" placeholder="Reason for the client (optional)"></textarea>
                    <div><button class="btn danger" type="submit">Reject</button></div>
                </form>
            @endif

            @if ($status === \App\Enums\AppointmentStatus::Approved)
                <div class="actions">
                    <form method="POST" action="{{ route('manage.appointments.complete', $appointment) }}">@csrf<button class="btn primary" type="submit">Mark completed</button></form>
                    <form method="POST" action="{{ route('manage.appointments.no-show', $appointment) }}" onsubmit="return confirm('Mark as no-show? This counts as a strike.')">@csrf<button class="btn danger" type="submit">No-show</button></form>
                </div>
            @endif

            @if (in_array($status, [\App\Enums\AppointmentStatus::Pending, \App\Enums\AppointmentStatus::Approved], true))
                <div class="actions"><a class="btn" href="{{ route('manage.appointments.reschedule', $appointment) }}">Reschedule</a></div>
                <form method="POST" action="{{ route('manage.appointments.cancel', $appointment) }}" class="inline-form" onsubmit="return confirm('Cancel this appointment?')">
                    @csrf
                    <input type="text" name="admin_note" maxlength="500" placeholder="Note for the client (optional)">
                    <div><button class="btn danger" type="submit">Cancel appointment</button></div>
                </form>
            @else
                @if (! in_array($status, [\App\Enums\AppointmentStatus::Pending, \App\Enums\AppointmentStatus::Approved], true) && $status !== \App\Enums\AppointmentStatus::Approved)
                    <p class="muted">No actions left for a {{ strtolower($status->label()) }} appointment.</p>
                @endif
            @endif
        </div>
    </div>

    @if ($supportRequests->isNotEmpty())
        <h2 class="section-title">Support requests about this appointment</h2>
        <div class="card">
            @foreach ($supportRequests as $item)
                <div class="notice-row"><div><strong>{{ $item->subject }}</strong><p>{{ $item->message }}</p></div><small class="muted">{{ ucfirst($item->status) }}</small></div>
            @endforeach
            <a class="btn small" href="{{ route('manage.support.index') }}">Open support inbox</a>
        </div>
    @endif
@endsection
