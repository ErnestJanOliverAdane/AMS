{{-- Needs: $analytics, $vault, $pending, $today --}}
@include('partials.analytics')

<div class="grid2">
    <div class="card">
        <h3 style="margin-bottom:10px">Waiting for approval</h3>
        @forelse ($pending as $row)
            <div class="notice-row">
                <div><strong>{{ $row->user->name }}</strong><p class="muted" style="font-size:13px">{{ $row->service->name }} · {{ $row->dateLabel() }}, {{ $row->timeLabel() }}</p></div>
                <a class="btn small" href="{{ route('manage.appointments.show', $row) }}">Review</a>
            </div>
        @empty
            <p class="muted">Nothing is waiting.</p>
        @endforelse
    </div>
    <div class="card">
        <h3 style="margin-bottom:10px">Today</h3>
        @forelse ($today as $row)
            <div class="notice-row">
                <div><strong>{{ substr($row->start_time, 0, 5) }} · {{ $row->user->name }}</strong><p class="muted" style="font-size:13px">{{ $row->service->name }}</p></div>
                <x-status :value="$row->status" />
            </div>
        @empty
            <p class="muted">No appointments today.</p>
        @endforelse
    </div>
</div>
