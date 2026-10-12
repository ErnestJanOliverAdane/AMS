@extends('layouts.app')

@section('title', 'Site layouts')

@section('content')
    <div class="page-head">
        <div>
            <h1>Site layouts</h1>
            <p>Auto-generated visual layouts for CCTV, WiFi, cabinets, cabling, paging, termination and IP PBX.</p>
        </div>
        <a class="btn primary" href="{{ route('layouts.create') }}">New layout</a>
    </div>

    <form class="card quick" method="POST" action="{{ route('layouts.quick') }}">
        @csrf
        <div>
            <h3>Scan a room with your camera</h3>
            <p class="muted">Opens your laptop or phone camera and draws a complete cabling layout over the live view, built to TIA / ISO network cabling standards.</p>
        </div>
        <label>Room width (m)<input type="number" name="width_m" min="3" max="500" step="0.5" value="{{ old('width_m', 12) }}" required></label>
        <button class="btn primary" type="submit">Open camera</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Services</th>@if ($staff)<th>Owner</th>@endif<th>Plan</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            @forelse ($layouts as $layout)
                <tr>
                    <td><a href="{{ route('layouts.show', $layout) }}"><strong>{{ $layout->name }}</strong></a></td>
                    <td>
                        @foreach ($layout->layers as $key)<span class="chip">{{ \App\Models\SiteLayout::LAYERS[$key] ?? $key }}</span>@endforeach
                    </td>
                    @if ($staff)<td>{{ $layout->user?->name }}</td>@endif
                    <td>{{ $layout->plan_name ?: 'Blank grid' }}</td>
                    <td class="num">{{ $layout->updated_at?->timezone(config('appointments.timezone'))->format('M j, g:i A') }}</td>
                    <td class="actions">
                        <a class="btn small" href="{{ route('layouts.show', $layout) }}">Open</a>
                        <form method="POST" action="{{ route('layouts.destroy', $layout) }}" onsubmit="return confirm('Delete this layout?')">
                            @csrf @method('DELETE')
                            <button class="btn small" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No layouts yet. Create one and upload your floor plan PDF.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $layouts->links('pagination::bootstrap-4') }}
@endsection
