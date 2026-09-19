@extends('layouts.app')

@section('title', $member->full_name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $member->full_name }}</h1>
        <p>Member profile · <span class="code-pill">{{ $member->member_code }}</span></p>
    </div>
    <div class="toolbar-actions">
        @perm('payments.create')
        <a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="btn btn-primary">Collect Fee</a>
        @endperm
        @perm('members.edit')
        <a href="{{ route('members.edit', $member) }}" class="btn btn-secondary">Edit</a>
        @endperm
        <a href="{{ route('members.index') }}" class="btn btn-ghost">Back</a>
    </div>
</div>

<div class="mini-stats" style="grid-template-columns:repeat(3,1fr)">
    <div class="mini-stat">
        <div class="label">Fee payments</div>
        <div class="value">{{ number_format($feeStats['payments']) }}</div>
    </div>
    <div class="mini-stat">
        <div class="label">Total paid</div>
        <div class="value" style="color:var(--green)">{{ money($feeStats['paid'], 0) }}</div>
    </div>
    <div class="mini-stat">
        <div class="label">Fee periods</div>
        <div class="value">{{ number_format($feeStats['periods']) }}</div>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div class="detail-hero">
            @if($member->avatar_url)
                <img src="{{ $member->avatar_url }}" alt="" class="avatar-lg">
            @else
                <div class="avatar-lg initials">{{ $member->initials }}</div>
            @endif
            <div>
                <h2 style="font-size:20px;margin-bottom:6px">{{ $member->full_name }}</h2>
                <span class="status-badge {{ $member->status }}">{{ $member->status }}</span>
                @if($member->activeSubscription?->plan)
                    <div style="margin-top:8px;font-size:13px;color:var(--text-dim)">
                        Package: <strong style="color:var(--text)">{{ $member->activeSubscription->plan->name }}</strong>
                    </div>
                @endif
            </div>
        </div>
        <div class="detail-list">
            <div class="dl-label">Package</div>
            <div class="dl-value">
                @php $active = $member->activeSubscription; @endphp
                @if($active?->plan)
                    <strong>{{ $active->plan->name }}</strong>
                    <span style="color:var(--text-mute)"> · {{ money($active->plan->price) }}</span>
                @elseif($active)
                    <span style="color:var(--text-mute)">No package assigned</span>
                @else
                    <span style="color:var(--text-mute)">—</span>
                @endif
            </div>
            <div class="dl-label">Email</div><div class="dl-value">{{ $member->email ?? '—' }}</div>
            <div class="dl-label">Phone</div><div class="dl-value">{{ $member->phone ?? '—' }}</div>
            <div class="dl-label">Biometric ID</div><div class="dl-value"><span class="code-pill">{{ $member->device_user_id ?: '—' }}</span></div>
            <div class="dl-label">Gender</div><div class="dl-value">{{ $member->gender ? ucfirst($member->gender) : '—' }}</div>
            <div class="dl-label">Trainer</div><div class="dl-value">{{ $member->trainer?->full_name ?? 'Self training' }}</div>
            <div class="dl-label">Date of Birth</div><div class="dl-value">{{ $member->date_of_birth?->format('M d, Y') ?? '—' }}</div>
            <div class="dl-label">Joined</div><div class="dl-value">{{ $member->joined_at?->format('M d, Y') ?? '—' }}</div>
            <div class="dl-label">Address</div><div class="dl-value">{{ $member->address ?? '—' }}</div>
            <div class="dl-label">Emergency</div><div class="dl-value">{{ $member->emergency_contact ?? '—' }} {{ $member->emergency_phone ? '· '.$member->emergency_phone : '' }}</div>
            <div class="dl-label">Notes</div><div class="dl-value">{{ $member->notes ?? '—' }}</div>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Fee / Membership Periods</h3>
        @forelse($member->subscriptions as $sub)
            @php
                $expired = $sub->end_date->toDateString() < now()->toDateString();
                $expiring = ! $expired && $sub->end_date->toDateString() <= now()->addDays(7)->toDateString();
                $packageName = $sub->plan?->name;
            @endphp
            <div style="padding:12px 0;border-bottom:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                    <div>
                        <div style="font-weight:600">{{ $packageName ?: 'No package' }}</div>
                        @if($sub->plan)
                            <div style="font-size:12px;color:var(--text-mute);margin-top:2px">
                                Package price: {{ money($sub->plan->price) }}
                                · Paid {{ money($sub->amount_paid) }}
                                @php
                                    $shownBalance = (float) ($sub->balance_due ?? 0);
                                    $shownDiscount = max(0, (float) $sub->plan->price - (float) $sub->amount_paid - $shownBalance);
                                @endphp
                                @if($shownDiscount > 0)
                                    · Discount {{ money($shownDiscount) }}
                                @endif
                                @if($shownBalance > 0)
                                    · Balance {{ money($shownBalance) }}
                                @endif
                            </div>
                        @endif
                        <div style="font-size:12px;color:var(--text-mute);margin-top:3px">
                            From <strong>{{ $sub->start_date->format('M d, Y') }}</strong>
                            to <strong>{{ $sub->end_date->format('M d, Y') }}</strong>
                        </div>
                        <div style="font-size:12px;color:var(--text-mute);margin-top:2px">
                            Amount paid: {{ money($sub->amount_paid) }}
                            @if((float) ($sub->balance_due ?? 0) > 0)
                                · Balance due: <strong style="color:#f87171">{{ money($sub->balance_due) }}</strong>
                            @endif
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span class="status-badge {{ $sub->status }}">{{ $sub->status }}</span>
                        <div style="margin-top:6px">
                            @if($expired)
                                <span class="status-badge overdue">expired</span>
                            @elseif($expiring)
                                <span class="status-badge leave">expiring soon</span>
                            @else
                                <span class="status-badge active">valid</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No fee period assigned. Collect a fee to create the first period.</p>
        @endforelse
    </div>
</div>

<div class="form-card" style="margin-top:16px;padding:0;overflow:hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border)">
        <div>
            <h3 style="margin:0;font-size:15px">Complete Fee History</h3>
            <p style="margin:4px 0 0;font-size:12.5px;color:var(--text-dim)">All fee payments recorded for this member</p>
        </div>
        @perm('payments.create')
        <a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="btn btn-secondary btn-sm">Collect Fee</a>
        @endperm
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Package</th>
                    <th>Fee period</th>
                    <th>Method</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Received by</th>
                    <th class="num" style="text-align:right">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($member->payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('M d, Y') ?? '—' }}</td>
                        <td><span class="code-pill">{{ $payment->payment_number }}</span></td>
                        <td style="font-weight:600">{{ $payment->plan?->name ?: '—' }}</td>
                        <td>
                            @if($payment->fee_start_date && $payment->fee_end_date)
                                {{ $payment->fee_start_date->format('M d, Y') }}
                                <span style="color:var(--text-mute)">→</span>
                                {{ $payment->fee_end_date->format('M d, Y') }}
                            @else
                                <span style="color:var(--text-mute)">—</span>
                            @endif
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->method ?? 'cash')) }}</td>
                        <td>{{ $payment->account?->name ?: '—' }}</td>
                        <td><span class="status-badge {{ $payment->status }}">{{ $payment->status }}</span></td>
                        <td>{{ $payment->receiver?->name ?: '—' }}</td>
                        <td style="text-align:right;font-weight:700;color:var(--green)">{{ money($payment->amount) }}</td>
                        <td>
                            <a href="{{ route('payments.show', $payment) }}" class="btn-icon" title="View payment">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:28px;color:var(--text-dim)">
                            No fee payments yet.
                            @perm('payments.create')
                            <a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="link" style="margin-left:6px">Collect first fee</a>
                            @endperm
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($member->payments->where('status', 'completed')->count())
                <tfoot>
                    <tr>
                        <td colspan="8" style="text-align:right;font-weight:700">Total completed</td>
                        <td style="text-align:right;font-weight:800;color:var(--green)">{{ money($feeStats['paid']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
