@foreach($members as $member)
    @php
        $sub = $member->activeSubscription;
        $expired = $sub && $sub->end_date->toDateString() < now()->toDateString();
        $expiring = $sub && ! $expired && $sub->end_date->toDateString() <= now()->addDays(7)->toDateString();
        $balanceDue = round((float) ($sub?->balance_due ?? 0), 2);
    @endphp
    <tr>
        <td>
            <div class="cell-user">
                @if($member->avatar_url)
                    <img src="{{ $member->avatar_url }}" alt="" class="avatar-img">
                @else
                    <div class="avatar-initials">{{ $member->initials }}</div>
                @endif
                <div>
                    <span class="name">{{ $member->full_name }}</span>
                    <span class="sub">{{ $member->email ?? 'No email' }}</span>
                </div>
            </div>
        </td>
        <td>{{ $member->trainer?->full_name ?? 'Self training' }}</td>
        <td><span class="code-pill" style="background:rgba(32, 56, 224,.15);color:#2038e0">{{ $member->device_user_id ?: '—' }}</span></td>
        <td>{{ $member->phone ?? '—' }}</td>
        <td>
            @if($sub)
                <div style="font-size:12.5px">{{ $sub->start_date->format('M d, Y') }} → {{ $sub->end_date->format('M d, Y') }}</div>
                <div style="font-size:11px;color:var(--text-mute)">{{ $sub->plan?->name ?? 'No package' }}</div>
            @else
                <span style="color:var(--text-mute)">No fee period</span>
            @endif
        </td>
        <td>
            @if(!$sub)
                <span class="status-badge pending">none</span>
            @elseif($expired)
                <span class="status-badge overdue">expired</span>
            @elseif($expiring)
                <span class="status-badge leave">expiring</span>
            @else
                <span class="status-badge active">valid</span>
            @endif
        </td>
        <td>
            @if($balanceDue > 0)
                <div style="font-weight:700;color:#f87171">{{ money($balanceDue) }}</div>
                @perm('payments.create')
                <a
                    href="{{ route('payments.create', ['member_id' => $member->id, 'amount' => number_format($balanceDue, 2, '.', ''), 'collect_balance' => 1]) }}"
                    class="link"
                    style="font-size:11.5px"
                    title="Collect balance from member"
                >Collect balance</a>
                @endperm
            @else
                <span style="color:var(--text-mute)">—</span>
            @endif
        </td>
        <td><span class="status-badge {{ $member->status }}">{{ $member->status }}</span></td>
        <td>
            <div class="table-actions">
                <a href="{{ route('members.show', $member) }}" class="btn-icon" title="View">
                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                @perm('members.edit')
                <a href="{{ route('members.edit', $member) }}" class="btn-icon" title="Edit">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </a>
                @endperm
            </div>
        </td>
    </tr>
@endforeach
