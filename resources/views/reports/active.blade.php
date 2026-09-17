@extends('layouts.app')

@section('title', 'Reports · Active Members')

@section('content')
@include('reports._toolbar')

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Active + Fee Valid</div><div class="value" style="color:#22c55e">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">As Of</div><div class="value" style="font-size:18px">{{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}</div></div>
    <div class="mini-stat"><div class="label">Fee Amount Paid</div><div class="value">{{ money($stats['fee_amount']) }}</div></div>
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
                    <th>Package Price</th>
                    <th>Amount Paid</th>
                    <th>Fee From</th>
                    <th>Fee Expiry</th>
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
                        <td><strong>{{ $sub?->plan?->name ?? '—' }}</strong></td>
                        <td>{{ $sub?->plan ? money($sub->plan->price) : '—' }}</td>
                        <td>{{ $sub ? money($sub->amount_paid) : '—' }}</td>
                        <td>{{ $sub?->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $sub?->end_date?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--text-dim)">No active members with valid fee in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
