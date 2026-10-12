@extends('layouts.app')

@section('title', 'Confirmation slip')

@section('content')
    <div class="page-head no-print">
        <div>
            <h1>Confirmation slip</h1>
            <p>Print it or choose “Save as PDF” in the print window.</p>
        </div>
        <div class="actions">
            <button class="btn primary" type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn" href="{{ route('client.appointments.show', $appointment) }}">Back</a>
        </div>
    </div>

    <div class="slip">
        <div class="brand" style="padding:0;margin-bottom:14px">
            <img src="{{ asset('images/logo.png') }}" alt="" width="44" height="44">
            <span>{{ config('portal.name') }}</span>
        </div>
        <h1>Appointment confirmation</h1>
        <p class="muted"><x-status :value="$appointment->status" /></p>
        <div class="ref">{{ $appointment->reference }}</div>
        <dl class="kv">
            <dt>Name</dt><dd>{{ $appointment->user->name }}</dd>
            <dt>Service</dt><dd>{{ $appointment->service->name }}</dd>
            <dt>Date</dt><dd>{{ $appointment->dateLabel() }}</dd>
            <dt>Time</dt><dd>{{ $appointment->timeLabel() }}</dd>
        </dl>
        <p class="muted" style="margin-top:18px;font-size:13px">Please arrive 10 minutes early and show this slip or the reference number.</p>
    </div>
@endsection
