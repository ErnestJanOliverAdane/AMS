{{-- Needs: $slots, $selectable (bool) --}}
@if ($slots->isEmpty())
    <p class="muted" style="margin-bottom:16px">No times on this day. The office may be closed or the date is blocked.</p>
@else
    <div class="slots">
        @foreach ($slots as $slot)
            @php $free = $slot['remaining'] > 0; @endphp
            @if ($selectable)
                <label class="slot">
                    <input type="radio" name="start_time" value="{{ substr($slot['start'], 0, 5) }}" @disabled(! $free) @checked(old('start_time') === substr($slot['start'], 0, 5)) required>
                    <span>{{ $slot['label'] }}<small>{{ $free ? $slot['remaining'].' left' : 'Full' }}</small></span>
                </label>
            @else
                <div @class(['slot', 'taken' => ! $free])>
                    <span>{{ $slot['label'] }}<small>{{ $free ? $slot['remaining'].' left' : 'Full' }}</small></span>
                </div>
            @endif
        @endforeach
    </div>
@endif
