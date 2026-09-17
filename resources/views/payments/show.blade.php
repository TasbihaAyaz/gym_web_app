@extends('layouts.app')

@section('title', $payment->payment_number)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $payment->payment_number }}</h1>
        <p>Payment details · <span class="status-badge {{ $payment->status }}">{{ $payment->status }}</span></p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('payments.edit', $payment) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="form-card" style="max-width:720px">
    <div class="detail-list">
        <div class="dl-label">Member</div><div class="dl-value">{{ $payment->member?->full_name ?? '—' }}</div>
        <div class="dl-label">Package</div><div class="dl-value">{{ $payment->plan?->name ?? '—' }}</div>
        <div class="dl-label">Fee period</div>
        <div class="dl-value">
            @if($payment->fee_start_date && $payment->fee_end_date)
                {{ $payment->fee_start_date->format('M d, Y') }} → {{ $payment->fee_end_date->format('M d, Y') }}
            @else
                —
            @endif
        </div>
        <div class="dl-label">Account</div><div class="dl-value">{{ $payment->account?->name ?? '—' }}</div>
        <div class="dl-label">Amount</div><div class="dl-value" style="font-size:18px;font-weight:800">{{ money($payment->amount) }}</div>
        <div class="dl-label">Method</div><div class="dl-value">{{ ucfirst(str_replace('_',' ',$payment->method)) }}</div>
        <div class="dl-label">Date</div><div class="dl-value">{{ $payment->payment_date?->format('M d, Y') }}</div>
        <div class="dl-label">Reference</div><div class="dl-value">{{ $payment->reference ?: '—' }}</div>
        <div class="dl-label">Received By</div><div class="dl-value">{{ $payment->receiver?->name ?? '—' }}</div>
        <div class="dl-label">Notes</div><div class="dl-value">{{ $payment->notes ?: '—' }}</div>
    </div>
</div>
@endsection
