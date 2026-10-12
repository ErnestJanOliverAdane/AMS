@extends('layouts.app')

@section('title', 'Super Admin')

@section('content')
    <div class="page-head">
        <div>
            <h1>Super Admin dashboard</h1>
            <p>Every account in the portal, by role.</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('super-admin.admins.create') }}">Add admin</a>
            <a class="btn" href="{{ route('manage.users.create') }}">Add user</a>
        </div>
    </div>

    @include('partials.appointment-overview')

    <div class="stats">
        @foreach ($stats as $stat)
            <div class="stat">
                <span>{{ $stat['label'] }}</span>
                <b>{{ $stat['total'] }}</b>
                <small>{{ $stat['active'] }} active</small>
            </div>
        @endforeach
    </div>

    <h2 style="margin-bottom:14px;font-size:1.15rem">Newest accounts</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
            @forelse ($recent as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->email }}</td>
                    <td><span class="badge {{ $account->role->value }}">{{ $account->role->label() }}</span></td>
                    <td>@if ($account->is_active) Active @else <span class="badge off">Deactivated</span> @endif</td>
                    <td class="num">{{ $account->created_at?->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No accounts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="vault-bottom">@include('partials.vault', ['vault' => $vault])</div>
@endsection
