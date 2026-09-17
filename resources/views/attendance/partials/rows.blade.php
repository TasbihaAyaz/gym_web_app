@foreach($attendances as $row)
    @php
        $member = $row->member;
        $sub = $member?->activeSubscription;
        $endDate = $sub?->end_date;

        $feeState = 'none';
        $feeLabel = 'No package';

        if ($endDate) {
            $today = now()->startOfDay();
            $end = $endDate->copy()->startOfDay();

            if ($end->lt($today)) {
                $feeState = 'expired';
                $days = (int) $end->diffInDays($today);
                $feeLabel = $days <= 1 ? 'Expired' : 'Expired ' . $days . 'd ago';
            } elseif ($end->lte($today->copy()->addDays(7))) {
                $feeState = 'expiring';
                $days = (int) $today->diffInDays($end);
                $feeLabel = $days === 0 ? 'Expires today' : $days . 'd left';
            } else {
                $feeState = 'active';
                $feeLabel = (int) $today->diffInDays($end) . 'd left';
            }
        }
    @endphp
    <tr>
        <td>
            <div class="cell-user">
                <div class="avatar-initials">{{ $member?->initials ?? 'M' }}</div>
                <div>
                    <span class="name">{{ $member?->full_name ?? '—' }}</span>
                    <span class="sub">{{ $member?->member_code }}</span>
                </div>
            </div>
        </td>
        <td>{{ $row->attendance_date?->format('M d, Y') }}</td>
        <td style="font-weight:600;color:#22c55e">{{ $row->check_in ? \Carbon\Carbon::parse($row->check_in)->format('h:i A') : '—' }}</td>
        <td>{{ $sub?->plan?->name ?? '—' }}</td>
        <td>
            @if($endDate)
                <div>{{ $endDate->format('M d, Y') }}</div>
                <span class="status-badge {{ $feeState }}" style="margin-top:4px">{{ $feeLabel }}</span>
            @else
                <span class="status-badge none">No package</span>
            @endif
        </td>
        <td>{{ $member?->trainer?->full_name ?? 'Self training' }}</td>
    </tr>
@endforeach
