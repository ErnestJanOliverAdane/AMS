{{-- Needs: $analytics. Pure SVG + CSS; public/js/dashboard.js adds count-up, tooltips and the cipher animation. --}}
@php
    $a = $analytics;
    $W = 640; $H = 230; $L = 34; $R = 16; $T = 16; $B = 34;
    $n = count($a['daily']);
    $step = ($W - $L - $R) / ($n - 1);
    $maxY = max(4, $a['maxDaily']);
    $pts = [];
    foreach ($a['daily'] as $i => $d) {
        $pts[$i] = [round($L + $i * $step, 1), round($H - $B - ($d['total'] / $maxY) * ($H - $B - $T), 1)];
    }
    $todayIdx = collect($a['daily'])->search(fn ($d) => $d['today']);
    $past = array_slice($pts, 0, $todayIdx + 1);
    $future = array_slice($pts, $todayIdx);
    $path = fn ($p) => 'M'.implode(' L', array_map(fn ($q) => $q[0].' '.$q[1], $p));
    $area = $path($past).' L'.$past[array_key_last($past)][0].' '.($H - $B).' L'.$past[0][0].' '.($H - $B).' Z';
    $glyph = ['pending' => '◔', 'approved' => '✓', 'completed' => '✔', 'rejected' => '✕', 'cancelled' => '⊘', 'no_show' => '!'];
    $maxService = max(1, collect($a['topServices'])->max('total') ?? 1);
    $maxDow = max(1, max($a['weekdays']));
    $dow = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $delta = $a['delta'];
@endphp

<div class="an" data-analytics>
    <div class="an-kpis">
        <div class="kpi">
            <span>Visits this week</span>
            <b><span data-count="{{ $a['thisWeek'] }}">{{ $a['thisWeek'] }}</span></b>
            <small class="{{ $delta === null ? 'muted' : ($delta >= 0 ? 'up' : 'down') }}">
                @if ($delta === null) No data for last week @else {{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}% vs last week ({{ $a['lastWeek'] }}) @endif
            </small>
        </div>
        <div class="kpi">
            <span>Approval rate</span>
            <b>@if ($a['approvalRate'] === null) – @else <span data-count="{{ $a['approvalRate'] }}">{{ $a['approvalRate'] }}</span>% @endif</b>
            <small class="muted">Last 90 days</small>
        </div>
        <div class="kpi">
            <span>No-show rate</span>
            <b>@if ($a['noShowRate'] === null) – @else <span data-count="{{ $a['noShowRate'] }}">{{ $a['noShowRate'] }}</span>% @endif</b>
            <small class="muted">Of attended or missed</small>
        </div>
        <div class="kpi">
            <span>Client rating</span>
            <b>@if ($a['rating'] === null) – @else {{ number_format($a['rating'], 1) }}<i>/5</i> @endif</b>
            <small class="muted">{{ $a['ratingCount'] }} {{ \Illuminate\Support\Str::plural('review', $a['ratingCount']) }}</small>
        </div>
    </div>

    <div class="an-grid">
        <section class="card an-card an-wide" aria-labelledby="an-trend">
            <header>
                <div><h3 id="an-trend">Appointments by visit date</h3><p class="muted">Last 7 days and the next 7</p></div>
                <button type="button" class="btn small" data-table-toggle aria-pressed="false">Table view</button>
            </header>
            <div class="chart" data-chart>
                <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Line chart of appointments per day for the last 7 and next 7 days">
                    @foreach ([0, .5, 1] as $g)
                        @php $gy = round($H - $B - $g * ($H - $B - $T), 1); @endphp
                        <line class="grid" x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $gy }}" y2="{{ $gy }}"/>
                        <text class="axis" x="{{ $L - 8 }}" y="{{ $gy + 4 }}" text-anchor="end">{{ round($g * $maxY) }}</text>
                    @endforeach
                    <line class="today-line" x1="{{ $pts[$todayIdx][0] }}" x2="{{ $pts[$todayIdx][0] }}" y1="{{ $T }}" y2="{{ $H - $B }}"/>
                    <text class="axis today-lbl" x="{{ $pts[$todayIdx][0] }}" y="{{ $T - 4 }}" text-anchor="middle">Today</text>
                    <path class="area" d="{{ $area }}"/>
                    <path class="line draw" d="{{ $path($past) }}" pathLength="1"/>
                    @if (count($future) > 1)<path class="line future draw" d="{{ $path($future) }}" pathLength="1"/>@endif
                    @foreach ($a['daily'] as $i => $d)
                        @if ($i % 2 === 0 || $d['today'])
                            <text class="axis" x="{{ $pts[$i][0] }}" y="{{ $H - 12 }}" text-anchor="middle">{{ $d['short'] }}</text>
                        @endif
                        <circle class="dot {{ $d['future'] ? 'future' : '' }}" cx="{{ $pts[$i][0] }}" cy="{{ $pts[$i][1] }}" r="4"/>
                        <rect class="hit" x="{{ $pts[$i][0] - $step / 2 }}" y="{{ $T }}" width="{{ $step }}" height="{{ $H - $B - $T }}"
                              data-tip="{{ $d['label'] }}: {{ $d['total'] }} {{ $d['total'] === 1 ? 'appointment' : 'appointments' }}{{ $d['future'] ? ' (scheduled)' : '' }}"
                              data-x="{{ $pts[$i][0] }}" data-y="{{ $pts[$i][1] }}"/>
                    @endforeach
                    <circle class="focus" r="6" cx="-10" cy="-10"/>
                </svg>
                <div class="tip" role="status" hidden></div>
            </div>
            <div class="table-view" hidden>
                <table>
                    <thead><tr><th>Date</th><th class="num">Appointments</th></tr></thead>
                    <tbody>@foreach ($a['daily'] as $d)<tr><td>{{ $d['label'] }}{{ $d['today'] ? ' (today)' : '' }}</td><td class="num">{{ $d['total'] }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </section>

        <section class="card an-card" aria-labelledby="an-status">
            <header><div><h3 id="an-status">Status mix</h3><p class="muted">{{ $a['statusTotal'] }} appointments in total</p></div></header>
            <div class="stack-bar" role="img" aria-label="Share of appointments by status">
                @foreach ($a['statuses'] as $s)
                    @if ($s['count'] > 0)
                        <span class="seg st-{{ $s['key'] }}" style="flex-grow:{{ $s['count'] }}" data-tip="{{ $s['label'] }}: {{ $s['count'] }} ({{ $s['percent'] }}%)"></span>
                    @endif
                @endforeach
                @if ($a['statusTotal'] === 0)<span class="seg empty" style="flex-grow:1"></span>@endif
            </div>
            <ul class="legend">
                @foreach ($a['statuses'] as $s)
                    <li><i class="sw st-{{ $s['key'] }}"><span aria-hidden="true">{{ $glyph[$s['key']] }}</span></i><span>{{ $s['label'] }}</span><b>{{ $s['count'] }}</b></li>
                @endforeach
            </ul>
            <div class="tip" role="status" hidden></div>
        </section>

        <section class="card an-card" aria-labelledby="an-services">
            <header><div><h3 id="an-services">Top services</h3><p class="muted">Last 30 days</p></div></header>
            @forelse ($a['topServices'] as $row)
                <div class="hbar" data-tip="{{ $row['name'] }}: {{ $row['total'] }}">
                    <span class="name">{{ $row['name'] }}</span>
                    <span class="track"><span class="fill" style="--w:{{ round($row['total'] / $maxService * 100) }}%"></span></span>
                    <b>{{ $row['total'] }}</b>
                </div>
            @empty
                <p class="muted">No appointments yet.</p>
            @endforelse
            <div class="tip" role="status" hidden></div>
        </section>

        <section class="card an-card" aria-labelledby="an-dow">
            <header><div><h3 id="an-dow">Busiest weekdays</h3><p class="muted">Last 90 days</p></div></header>
            <div class="dow">
                @foreach ($dow as $num => $name)
                    @php $v = $a['weekdays'][$num]; @endphp
                    <div class="col" data-tip="{{ $name }}: {{ $v }}">
                        <span class="dbar"><i style="--h:{{ round($v / $maxDow * 100) }}%"></i></span>
                        <small>{{ $name }}</small><b>{{ $v }}</b>
                    </div>
                @endforeach
            </div>
            <div class="tip" role="status" hidden></div>
        </section>

        <section class="card an-card" aria-labelledby="an-ops">
            <header><div><h3 id="an-ops">Needs attention</h3><p class="muted">Open items right now</p></div></header>
            <ul class="ops">
                <li><a href="{{ route('manage.appointments.index', ['status' => 'pending']) }}"><span>Awaiting approval</span><b>{{ $a['statuses'][0]['count'] }}</b></a></li>
                <li><a href="{{ route('manage.inquiries.index') }}"><span>New guest messages</span><b>{{ $a['ops']['guests'] }}</b></a></li>
                <li><a href="{{ route('manage.support.index') }}"><span>Open support requests</span><b>{{ $a['ops']['support'] }}</b></a></li>
                <li><a href="{{ route('manage.clients.index') }}"><span>Restricted clients</span><b>{{ $a['ops']['restricted'] }}</b></a></li>
            </ul>
        </section>

    </div>
</div>
