@extends('layouts.app')

@section('title', 'Schedule')

@section('content')
    <div class="page-head">
        <div>
            <h1>Schedule</h1>
            <p>Working hours, breaks and how many clients fit in the same time slot. Slots are {{ config('appointments.slot_interval_minutes') }} minutes apart.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('manage.schedule.hours') }}">
        @csrf
        @method('PUT')
        <div class="table-wrap">
            <table>
                <thead><tr><th>Day</th><th>Open</th><th>Opens</th><th>Closes</th><th>Break from</th><th>Break to</th><th>Per slot</th></tr></thead>
                <tbody>
                @foreach (\App\Models\WorkingHour::DAYS as $weekday => $name)
                    @php
                        $h = $hours->get($weekday);
                        $t = fn ($col, $default = null) => old("days.$weekday.$col", $h?->$col ? substr($h->$col, 0, 5) : $default);
                    @endphp
                    <tr>
                        <td><strong>{{ $name }}</strong></td>
                        <td><input type="checkbox" name="open[{{ $weekday }}]" value="1" @checked(old('open') !== null ? old("open.$weekday") : ($h?->is_open ?? false)) aria-label="{{ $name }} is open"></td>
                        <td><input type="time" name="days[{{ $weekday }}][opens_at]" value="{{ $t('opens_at', '08:00') }}" required></td>
                        <td><input type="time" name="days[{{ $weekday }}][closes_at]" value="{{ $t('closes_at', '17:00') }}" required></td>
                        <td><input type="time" name="days[{{ $weekday }}][break_starts_at]" value="{{ $t('break_starts_at') }}"></td>
                        <td><input type="time" name="days[{{ $weekday }}][break_ends_at]" value="{{ $t('break_ends_at') }}"></td>
                        <td><input type="number" min="1" max="20" name="days[{{ $weekday }}][capacity]" value="{{ old("days.$weekday.capacity", $h?->capacity ?? 1) }}" style="width:80px" required></td>
                    </tr>
                    @foreach (['opens_at', 'closes_at', 'break_starts_at', 'capacity'] as $col)
                        @error("days.$weekday.$col")<tr><td colspan="7" style="color:var(--danger)">{{ $message }}</td></tr>@enderror
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px"><button class="btn primary" type="submit">Save working hours</button></div>
    </form>

    <h2 class="section-title">Blocked dates</h2>
    <div class="grid2">
        <form class="card" method="POST" action="{{ route('manage.schedule.blocked.store') }}">
            @csrf
            <div class="field">
                <label for="bdate">Date</label>
                <input id="bdate" type="date" name="date" value="{{ old('date') }}" required>
                @error('date') <span class="err">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="breason">Reason</label>
                <input id="breason" type="text" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="Holiday, training, …">
            </div>
            <button class="btn primary" type="submit">Block date</button>
        </form>

        <div class="card">
            @forelse ($blocked as $item)
                <div class="notice-row">
                    <div><strong>{{ $item->date->format('D, M j, Y') }}</strong><p class="muted">{{ $item->reason ?: 'No reason given' }}</p></div>
                    <form method="POST" action="{{ route('manage.schedule.blocked.destroy', $item) }}">@csrf @method('DELETE')<button class="btn small" type="submit">Open again</button></form>
                </div>
            @empty
                <p class="muted">No blocked dates.</p>
            @endforelse
        </div>
    </div>
@endsection
