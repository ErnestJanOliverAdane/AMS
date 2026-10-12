@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
    @php $q = fn (array $extra) => route('manage.calendar', array_filter($extra + ['view' => $view, 'all' => $showAll ? 1 : null])); @endphp
    <div class="page-head">
        <div>
            <h1>Calendar</h1>
            <p>Daily, weekly and monthly view of appointments.</p>
        </div>
        <div class="tabs" style="margin:0">
            @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
                <a href="{{ route('manage.calendar', array_filter(['view' => $key, 'date' => $anchor, 'all' => $showAll ? 1 : null])) }}" @class(['on' => $view === $key])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="cal-head">
        <div class="actions">
            <a class="btn small" href="{{ $q(['date' => $prev]) }}" aria-label="Previous">‹</a>
            <a class="btn small" href="{{ $q(['date' => $today]) }}">Today</a>
            <a class="btn small" href="{{ $q(['date' => $next]) }}" aria-label="Next">›</a>
        </div>
        <h2>{{ $title }}</h2>
        <a class="btn small" href="{{ route('manage.calendar', array_filter(['view' => $view, 'date' => $anchor, 'all' => $showAll ? null : 1])) }}">{{ $showAll ? 'Hide cancelled/rejected' : 'Show cancelled/rejected' }}</a>
    </div>

    <div @class(['cal', 'week' => $view === 'week', 'dayview' => $view === 'day'])>
        @if ($view !== 'day')
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)<div class="cal-dow">{{ $dow }}</div>@endforeach
        @endif

        @foreach ($days as $day)
            <div @class(['day', 'out' => ! $day['in_month'] && $view === 'month', 'today' => $day['is_today']])>
                <a class="dnum" href="{{ route('manage.calendar', ['view' => 'day', 'date' => $day['date']]) }}">{{ $view === 'month' ? $day['day'] : $day['label'] }}</a>
                @foreach ($day['items'] as $item)
                    <a href="{{ route('manage.appointments.show', $item) }}" class="ev {{ $item->status->value }}" title="{{ $item->user->name }} · {{ $item->service->name }} · {{ $item->status->label() }}">
                        {{ substr($item->start_time, 0, 5) }} {{ $item->user->name }}@if ($view !== 'month') · {{ $item->service->name }}@endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
@endsection
