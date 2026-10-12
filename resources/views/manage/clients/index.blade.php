@extends('layouts.app')

@section('title', 'Clients')

@section('content')
    <div class="page-head">
        <div>
            <h1>Clients</h1>
            <p>Restrict booking for clients who keep cancelling or missing appointments. {{ config('appointments.restriction.threshold') }} strikes in {{ config('appointments.restriction.window_days') }} days restrict an account automatically.</p>
        </div>
    </div>

    <form method="GET" class="filters">
        <label>Search<input type="text" name="q" value="{{ $q }}" placeholder="Name or email"></label>
        <label class="check" style="margin:0"><input type="checkbox" name="restricted" value="1" @checked(request()->boolean('restricted'))> Restricted only</label>
        <button class="btn" type="submit">Filter</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Client</th><th>Strikes</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td><strong>{{ $client->name }}</strong><br><small class="muted">{{ $client->email }}</small></td>
                    <td>{{ $strikes[$client->id] ?? 0 }}</td>
                    <td>
                        @if ($client->isBookingRestricted())
                            <span class="badge off">Restricted</span><br><small class="muted">{{ $client->restriction_reason }}</small>
                        @else Can book @endif
                    </td>
                    <td class="row-actions">
                        @if ($client->isBookingRestricted())
                            <form method="POST" action="{{ route('manage.clients.lift', $client) }}">@csrf<button class="btn small" type="submit">Lift restriction</button></form>
                        @else
                            <form method="POST" action="{{ route('manage.clients.restrict', $client) }}" class="actions" onsubmit="return confirm('Restrict this client?')">
                                @csrf
                                <input type="text" name="reason" maxlength="255" placeholder="Reason" style="width:150px;padding:6px 9px;border-radius:8px;border:1px solid var(--line);background:var(--surface-2);color:var(--fg)">
                                <button class="btn small danger" type="submit">Restrict</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No clients found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $clients->links('pagination::bootstrap-4') }}
@endsection
