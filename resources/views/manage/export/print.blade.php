@extends('layouts.app')

@section('title', 'Appointment list')

@section('content')
    <div class="page-head no-print">
        <div>
            <h1>Appointment list</h1>
            <p>Use “Print / Save as PDF”. Choose “Save as PDF” as the printer.</p>
        </div>
        <div class="actions">
            <button class="btn primary" type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn" href="{{ route('manage.appointments.index') }}">Back</a>
        </div>
    </div>

    <h2 style="margin-bottom:4px">{{ config('portal.name') }} · Appointments</h2>
    <p class="muted" style="margin-bottom:14px">
        Generated {{ $generatedAt->format('M j, Y g:i A') }}
        @if (! empty($filters['from']) || ! empty($filters['to'])) · {{ $filters['from'] ?? '…' }} → {{ $filters['to'] ?? '…' }}@endif
        @if (! empty($filters['status'])) · {{ ucfirst(str_replace('_', ' ', $filters['status'])) }}@endif
        · {{ $appointments->count() }} rows
    </p>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Ref</th><th>Date</th><th>Time</th><th>Client</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($appointments as $row)
                <tr>
                    <td class="mono">{{ $row->reference }}</td>
                    <td>{{ $row->date->format('Y-m-d') }}</td>
                    <td>{{ substr($row->start_time, 0, 5) }}–{{ substr($row->end_time, 0, 5) }}</td>
                    <td>{{ $row->user->name }}</td>
                    <td>{{ $row->service->name }}</td>
                    <td>{{ $row->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No appointments match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
