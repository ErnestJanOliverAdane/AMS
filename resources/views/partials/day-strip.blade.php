{{-- Needs: $days, $date, optional $service --}}
<div class="dstrip" role="list">
    @foreach ($days as $d)
        <a role="listitem"
           href="{{ request()->url().'?'.http_build_query(array_filter(['service_id' => $service?->id ?? null, 'date' => $d['date']])) }}"
           @class(['dchip', 'on' => $d['date'] === $date, 'full' => $d['open'] === 0])>
            <small>{{ $d['dow'] }}</small>
            <b>{{ $d['day'] }}</b>
            <small>{{ $d['month'] }}</small>
            <em>{{ $d['open'] > 0 ? $d['open'].' open' : 'full' }}</em>
        </a>
    @endforeach
</div>
