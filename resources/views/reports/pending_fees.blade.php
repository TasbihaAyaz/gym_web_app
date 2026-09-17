@extends('layouts.app')

@section('title', 'Reports · Fee Pending')

@section('content')
@include('reports._toolbar')

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Pending Members</div><div class="value" style="color:#f59e0b">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Fee Due</div><div class="value" style="color:#ef4444">{{ money($stats['due_amount']) }}</div></div>
    <div class="mini-stat"><div class="label">Avg Days Overdue</div><div class="value">{{ number_format($stats['avg_overdue']) }}</div></div>
    <div class="mini-stat"><div class="label">As Of</div><div class="value" style="font-size:18px">{{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}</div></div>
</div>

<div class="form-card" style="padding:0;overflow:hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Code</th>
                    <th>Phone</th>
                    <th>Package</th>
                    <th>Fee Period</th>
                    <th>Days Overdue</th>
                    <th>Due Balance</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $member = $row['member']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('members.show', $member) }}" style="font-weight:600;color:inherit">{{ $member->full_name }}</a>
                        </td>
                        <td><span class="code-pill">{{ $member->member_code }}</span></td>
                        <td>{{ $member->phone ?? '—' }}</td>
                        <td>{{ $row['package'] ?? '—' }}</td>
                        <td style="font-size:12.5px">
                            @if($row['fee_start'] && $row['fee_end'])
                                {{ $row['fee_start']->format('M d, Y') }} → {{ $row['fee_end']->format('M d, Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($row['days_overdue'] > 0)
                                <span style="color:#ef4444;font-weight:700">{{ $row['days_overdue'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-weight:600;color:#ef4444">{{ money($row['due_balance']) }}</td>
                        <td><span class="status-badge overdue">{{ $row['reason'] }}</span></td>
                        <td><span class="status-badge {{ $member->status }}">{{ $member->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;padding:28px;color:var(--text-dim)">No pending fees in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
