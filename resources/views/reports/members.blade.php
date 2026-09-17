@extends('layouts.app')

@section('title', 'Reports · Members')

@section('content')
@include('reports._toolbar')

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Members In Report</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active (of list)</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Payments In Range</div><div class="value" style="color:#22c55e">{{ money($stats['revenue']) }}</div></div>
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
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Paid In Range</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                    @php $sub = $member->activeSubscription; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('members.show', $member) }}" style="font-weight:600;color:inherit">{{ $member->full_name }}</a>
                        </td>
                        <td><span class="code-pill">{{ $member->member_code }}</span></td>
                        <td>{{ $member->phone ?? '—' }}</td>
                        <td>{{ $sub?->plan?->name ?? '—' }}</td>
                        <td style="font-size:12.5px">
                            @if($sub)
                                {{ $sub->start_date->format('M d, Y') }} → {{ $sub->end_date->format('M d, Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $member->joined_at?->format('M d, Y') ?? '—' }}</td>
                        <td><span class="status-badge {{ $member->status }}">{{ $member->status }}</span></td>
                        <td style="font-weight:600">{{ money($paidInRange[$member->id] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--text-dim)">No members matched this date range (joined, fee period, or payment).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
