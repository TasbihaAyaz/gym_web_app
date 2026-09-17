@extends('layouts.app')

@section('title', 'Fee Payments')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Fee Payments</h1>
        <p>Membership fees collected from members</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('payments.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Collect Fee
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Payments</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Completed</div><div class="value" style="color:#22c55e">{{ number_format($stats['completed']) }}</div></div>
    <div class="mini-stat"><div class="label">Pending</div><div class="value" style="color:#3b82f6">{{ number_format($stats['pending']) }}</div></div>
    <div class="mini-stat"><div class="label">Collected</div><div class="value" style="color:#2038e0">{{ money($stats['amount']) }}</div></div>
</div>

<form method="GET" action="{{ route('payments.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search payment #, member, ref...">
    </div>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach(['completed','pending','failed','refunded'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
    </select>
    <select name="method" class="form-select" onchange="this.form.submit()">
        <option value="">All Methods</option>
        @foreach(['cash','card','bank_transfer','online','other'] as $m)
            <option value="{{ $m }}" @selected(request('method')===$m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status','method']))
        <a href="{{ route('payments.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($payments->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Member ID</th>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td><span class="code-pill">{{ $payment->payment_number }}</span></td>
                            <td style="font-weight:700;font-size:1.05rem;color:#000"><span class="code-pill" style="font-weight:700;font-size:1.05rem;color:#000;background:transparent;padding:0">{{ $payment->member?->member_code ?? '—' }}</span></td>
                            <td style="font-weight:600">{{ $payment->member?->full_name ?? '—' }}</td>
                            <td>{{ $payment->plan?->name ?? '—' }}</td>
                            <td>{{ $payment->payment_date?->format('M d, Y') }}</td>
                            <td>{{ ucfirst(str_replace('_',' ',$payment->method)) }}</td>
                            <td style="font-weight:700">{{ money($payment->amount) }}</td>
                            <td><span class="status-badge {{ $payment->status }}">{{ $payment->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('payments.show', $payment) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('payments.edit', $payment) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form action="{{ route('payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Delete this payment?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon danger" title="Delete">
                                            <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $payments])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg></div>
            <h3>No payments found</h3>
            <p>Collect a membership fee payment from a member.</p>
            <a href="{{ route('payments.create') }}" class="btn btn-primary">Collect Fee</a>
        </div>
    @endif
</div>
@endsection
