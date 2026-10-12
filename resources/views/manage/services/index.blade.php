@extends('layouts.app')

@section('title', 'Services')

@section('content')
    <div class="page-head">
        <div>
            <h1>Services</h1>
            <p>The options clients can choose when they book.</p>
        </div>
        <a class="btn primary" href="{{ route('manage.services.create') }}">Add service</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Duration</th><th>Appointments</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($services as $service)
                <tr>
                    <td><strong>{{ $service->name }}</strong><br><small class="muted">{{ \Illuminate\Support\Str::limit($service->description, 80) }}</small></td>
                    <td>{{ $service->durationLabel() }}</td>
                    <td>{{ $service->appointments_count }}</td>
                    <td>@if ($service->is_active) Active @else <span class="badge off">Hidden</span> @endif</td>
                    <td class="row-actions">
                        <a class="btn small" href="{{ route('manage.services.edit', $service) }}">Edit</a>
                        <form method="POST" action="{{ route('manage.services.destroy', $service) }}" onsubmit="return confirm('Delete this service?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No services yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
