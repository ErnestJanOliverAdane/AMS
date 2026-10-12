@extends('layouts.app')

@section('title', 'Admin')

@section('content')
    <div class="page-head">
        <div>
            <h1>Admin dashboard</h1>
            <p>Manage the User accounts of your portal.</p>
        </div>
        <a class="btn primary" href="{{ route('manage.users.create') }}">Add user</a>
    </div>

    @include('partials.appointment-overview')

    <div class="stats">
        <div class="stat"><span>Users</span><b>{{ $total }}</b><small>{{ $active }} active</small></div>
        <div class="stat"><span>Deactivated</span><b>{{ $total - $active }}</b><small>Cannot sign in</small></div>
    </div>

    <h2 style="margin-bottom:14px;font-size:1.15rem">Newest users</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
            @forelse ($recent as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->email }}</td>
                    <td>@if ($account->is_active) Active @else <span class="badge off">Deactivated</span> @endif</td>
                    <td class="num">{{ $account->created_at?->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No users yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="vault-bottom">@include('partials.vault', ['vault' => $vault])</div>
@endsection
