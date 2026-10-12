{{-- Needs: $rows (appointments), $staff (bool) --}}
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Reference</th>
            @if ($staff)<th>Client</th>@endif
            <th>Service</th>
            <th>When</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="mono">{{ $row->reference }}</td>
                @if ($staff)<td>{{ $row->user->name }}</td>@endif
                <td>{{ $row->service->name }}</td>
                <td class="num">{{ $row->dateLabel() }}<br><small class="muted">{{ $row->timeLabel() }}</small></td>
                <td><x-status :value="$row->status" /></td>
                <td class="row-actions">
                    <a class="btn small" href="{{ route($staff ? 'manage.appointments.show' : 'client.appointments.show', $row) }}">View</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $staff ? 6 : 5 }}" class="empty">{{ $empty ?? 'Nothing here yet.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
