@foreach($attendances as $row)
    @php
        $member = $row->member;
        $sub = $member?->activeSubscription;
        $endDate = $sub?->end_date;
        $monthlyFee = $sub ? (float) $sub->amount_paid : null;
        $balanceDue = $sub ? round((float) ($sub->balance_due ?? 0), 2) : 0;
        $planPrice = $sub?->plan ? (float) $sub->plan->price : null;

        // Fee status relative to the attendance day (not always "today").
        $asOf = ($row->attendance_date ?? now())->copy()->startOfDay();
        $feeState = 'none';
        $feeLabel = 'No package';

        if ($endDate) {
            $end = $endDate->copy()->startOfDay();

            if ($end->lt($asOf)) {
                $feeState = 'expired';
                $days = (int) $end->diffInDays($asOf);
                $feeLabel = $days <= 1 ? 'Expired' : 'Expired ' . $days . 'd ago';
            } elseif ($end->lte($asOf->copy()->addDays(7))) {
                $feeState = 'expiring';
                $days = (int) $asOf->diffInDays($end);
                $feeLabel = $days === 0 ? 'Expires today' : $days . 'd left';
            } else {
                $feeState = 'active';
                $feeLabel = (int) $asOf->diffInDays($end) . 'd left';
            }
        }
    @endphp
    <tr>
        <td>
            <div class="cell-user">
                <div class="avatar-initials">{{ $member?->initials ?? 'M' }}</div>
                <div>
                    <span class="name">{{ $member?->full_name ?? '—' }}</span>
                    <span class="sub">Bio {{ $member?->device_user_id ?: '—' }} · {{ $member?->member_code }}</span>
                </div>
            </div>
        </td>
        <td>{{ $row->attendance_date?->format('M d, Y') }}</td>
        <td style="font-weight:600;color:#22c55e">{{ $row->check_in ? \Carbon\Carbon::parse($row->check_in)->format('h:i A') : '—' }}</td>
        <td>
            {{ $sub?->plan?->name ?? '—' }}
            @if($planPrice !== null && $monthlyFee !== null && abs($planPrice - $monthlyFee) > 0.009)
                <div style="font-size:11px;color:var(--text-mute)">Pkg {{ money($planPrice) }}</div>
            @endif
        </td>
        <td>
            @if($monthlyFee !== null)
                <div style="font-weight:600">{{ money($monthlyFee) }}</div>
                @if($balanceDue > 0)
                    <div style="font-size:11px;color:var(--red);margin-top:2px">Bal {{ money($balanceDue) }}</div>
                @endif
            @else
                <span style="color:var(--text-mute)">—</span>
            @endif
        </td>
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
