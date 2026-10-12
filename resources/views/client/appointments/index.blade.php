@extends('layouts.app')

@section('title', 'My appointments')

@section('content')
    <div class="page-head">
        <div>
            <h1>My appointments</h1>
            <p>Upcoming visits and your history.</p>
        </div>
        <a class="btn primary" href="{{ route('client.appointments.create') }}">Book appointment</a>
    </div>

    <h2 class="section-title" style="margin-top:0">Upcoming</h2>
    @include('partials.appointment-table', ['rows' => $upcoming, 'staff' => false, 'empty' => 'No upcoming appointments.'])

    <h2 class="section-title">History</h2>
    @include('partials.appointment-table', ['rows' => $history, 'staff' => false, 'empty' => 'No past appointments yet.'])
@endsection
