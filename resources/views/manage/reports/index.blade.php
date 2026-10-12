@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reports</h1>
            <p>Appointment statistics per day and per month.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('manage.export.csv', ['from' => $from, 'to' => $to]) }}">Export CSV</a>
            <a class="btn" href="{{ route('manage.export.print', ['from' => $from, 'to' => $to]) }}" target="_blank" rel="noopener">Print / PDF</a>
        </div>
    </div>

    <form method="GET" class="filters">
        <label>From<input type="date" name="from" value="{{ $from }}"></label>
        <label>To<input type="date" name="to" value="{{ $to }}"></label>
        <button class="btn" type="submit">Update</button>
    </form>

    <div class="stats">
        <div class="stat"><span>Total</span><b>{{ $summary['total'] }}</b><small>{{ $from }} → {{ $to }}</small></div>
        <div class="stat"><span>Completed</span><b>{{ $summary['completed'] }}</b><small>Approved: {{ $summary['approved'] }}</small></div>
        <div class="stat"><span>Pending</span><b>{{ $summary['pending'] }}</b><small>Waiting for a decision</small></div>
        <div class="stat"><span>Cancelled / no-show</span><b>{{ $summary['cancelled'] + $summary['no_show'] }}</b><small>Rejected: {{ $summary['rejected'] }}</small></div>
        <div class="stat"><span>Average rating</span><b>{{ $averageRating ? number_format($averageRating, 1) : '–' }}</b><small>{{ $ratingCount }} reviews</small></div>
    </div>

    <div class="grid2">
        <div class="card">
            <h2 style="font-size:1.1rem;margin-bottom:12px">Per day</h2>
            <div class="scroll-y">
                @foreach ($perDay as $row)
                    <div class="barrow">
                        <span>{{ $row['label'] }}</span>
                        <div class="bar" title="{{ $row['total'] }} total">
                            <i style="width:{{ round($row['completed'] / $maxDay * 100, 1) }}%"></i>
                            <i class="b4" style="width:{{ round(($row['approved']) / $maxDay * 100, 1) }}%"></i>
                            <i class="b2" style="width:{{ round($row['pending'] / $maxDay * 100, 1) }}%"></i>
                            <i class="b3" style="width:{{ round(($row['cancelled'] + $row['no_show'] + $row['rejected']) / $maxDay * 100, 1) }}%"></i>
                        </div>
                        <b>{{ $row['total'] }}</b>
                    </div>
                @endforeach
            </div>
            <p class="muted" style="font-size:12px;margin-top:10px">Green completed · blue approved · amber pending · red cancelled, no-show or rejected.</p>
        </div>

        <div class="card">
            <h2 style="font-size:1.1rem;margin-bottom:12px">Per month (last 12)</h2>
            @foreach ($perMonth as $row)
                <div class="barrow">
                    <span>{{ $row['label'] }}</span>
                    <div class="bar">
                        <i style="width:{{ round($row['completed'] / $maxMonth * 100, 1) }}%"></i>
                        <i class="b4" style="width:{{ round($row['approved'] / $maxMonth * 100, 1) }}%"></i>
                        <i class="b2" style="width:{{ round($row['pending'] / $maxMonth * 100, 1) }}%"></i>
                        <i class="b3" style="width:{{ round(($row['cancelled'] + $row['no_show'] + $row['rejected']) / $maxMonth * 100, 1) }}%"></i>
                    </div>
                    <b>{{ $row['total'] }}</b>
                </div>
            @endforeach

            <h2 style="font-size:1.1rem;margin:22px 0 10px">Top services</h2>
            @forelse ($topServices as $row)
                <div class="barrow" style="grid-template-columns:minmax(0,1fr) 40px"><span>{{ $row['name'] }}</span><b>{{ $row['total'] }}</b></div>
            @empty
                <p class="muted">No data in this range.</p>
            @endforelse
        </div>
    </div>
@endsection
