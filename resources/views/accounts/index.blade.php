@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $accounts->total() }} {{ $accounts->total() === 1 ? $singular : $singular.'s' }}{{ $search !== '' ? ' matching "'.$search.'"' : '' }}</p>
        </div>
        <a class="btn primary" href="{{ route($routeBase.'.create') }}">Add {{ $singular }}</a>
    </div>

    @if ($errors->any())
        <div class="flash bad" role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="search" method="GET" action="{{ route($routeBase.'.index') }}" role="search">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or email" aria-label="Search">
        <button class="btn" type="submit">Search</button>
        @if ($search !== '')
            <a class="btn" href="{{ route($routeBase.'.index') }}">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->email }}</td>
                    <td>@if ($account->is_active) Active @else <span class="badge off">Deactivated</span> @endif</td>
                    <td class="num">{{ $account->created_at?->format('M j, Y') }}</td>
                    <td>
                        <div class="row-actions">
                            <a class="btn small" href="{{ route($routeBase.'.edit', $account) }}">Edit</a>
                            <form method="POST" action="{{ route($routeBase.'.destroy', $account) }}"
                                  onsubmit="return confirm('Delete {{ e(addslashes($account->name)) }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No {{ $singular }}s found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $accounts->links('pagination::bootstrap-4') }}
@endsection
