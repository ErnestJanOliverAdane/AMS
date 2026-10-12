@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
    <div class="page-head">
        <div>
            <h1>Appointments</h1>
            <p>Every client request in one place.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('manage.export.csv', array_filter($filters)) }}">Export CSV</a>
            <a class="btn" href="{{ route('manage.export.print', array_filter($filters)) }}" target="_blank" rel="noopener">Print / PDF</a>
        </div>
    </div>

    <div class="tabs">
        <a href="{{ route('manage.appointments.index') }}" @class(['on' => empty($filters['status'])])>All ({{ $counts['total'] }})</a>
        @foreach ($statuses as $status)
            <a href="{{ route('manage.appointments.index', array_filter(['status' => $status->value, 'q' => $filters['q'] ?? null])) }}" @class(['on' => ($filters['status'] ?? null) === $status->value])>{{ $status->label() }} ({{ $counts[$status->value] }})</a>
        @endforeach
    </div>

    <form method="GET" class="filters">
        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label>Search<input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, reference"></label>
        <label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button class="btn" type="submit">Filter</button>
    </form>

    @include('partials.appointment-table', ['rows' => $appointments, 'staff' => true, 'empty' => 'No appointments match.'])
    {{ $appointments->links('pagination::bootstrap-4') }}
@endsection
