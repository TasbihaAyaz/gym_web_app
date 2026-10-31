@foreach($staffRows as $row)
    @php
        $member = $row->member;
        $badge = match ($row->status) {
            'present' => 'active',
            'late' => 'leave',
            default => 'overdue',
        };
    @endphp
    <tr>
        <td>
            <div class="cell-user">
                <div class="avatar-initials">{{ $member?->initials ?? 'S' }}</div>
                <div>
                    <span class="name">{{ $member?->full_name ?? '—' }}</span>
                    <span class="sub">{{ $member?->member_code }} · Staff</span>
                </div>
            </div>
        </td>
        <td><span class="code-pill">{{ $member?->device_user_id ?: '—' }}</span></td>
        <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</td>
        <td style="font-weight:600;color:{{ $row->check_in ? '#22c55e' : 'var(--text-mute)' }}">
            {{ $row->check_in ? \Carbon\Carbon::parse($row->check_in)->format('h:i A') : '—' }}
        </td>
        <td><span class="status-badge {{ $badge }}">{{ $row->status_label }}</span></td>
        <td>{{ $row->method ? ucfirst($row->method) : '—' }}</td>
    </tr>
@endforeach
