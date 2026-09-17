@extends('layouts.app')

@section('title', $account->name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $account->name }}</h1>
        <p><span class="code-pill">{{ $account->code }}</span> · <span class="status-badge {{ $account->type }}">{{ $account->type }}</span></p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('accounts.edit', $account) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div class="balance" style="font-size:28px;font-weight:800;margin-bottom:16px">{{ money($account->current_balance) }}</div>
        <div class="detail-list">
            <div class="dl-label">Opening Balance</div><div class="dl-value">{{ money($account->opening_balance) }}</div>
            <div class="dl-label">Type</div><div class="dl-value">{{ ucfirst($account->type) }}</div>
            <div class="dl-label">Status</div><div class="dl-value"><span class="status-badge {{ $account->is_active ? 'active' : 'inactive' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></div>
            <div class="dl-label">Description</div><div class="dl-value">{{ $account->description ?: '—' }}</div>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Recent Activity</h3>
        <h4 style="font-size:12px;color:var(--text-mute);margin-bottom:8px;text-transform:uppercase;letter-spacing:.6px">Payments</h4>
        @forelse($account->payments as $payment)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;font-size:13px">
                <span>{{ $payment->payment_number }}</span>
                <strong style="color:#22c55e">+{{ money($payment->amount) }}</strong>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:12.5px;margin-bottom:12px">No payments</p>
        @endforelse

        <h4 style="font-size:12px;color:var(--text-mute);margin:14px 0 8px;text-transform:uppercase;letter-spacing:.6px">Expenses</h4>
        @forelse($account->expenses as $expense)
            <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;font-size:13px">
                <span>{{ $expense->title }}</span>
                <strong style="color:#ef4444">-{{ money($expense->amount) }}</strong>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:12.5px">No expenses</p>
        @endforelse
    </div>
</div>
@endsection
