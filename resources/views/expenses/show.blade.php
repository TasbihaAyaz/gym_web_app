@extends('layouts.app')

@section('title', $expense->expense_number)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $expense->category }}</h1>
        <p>{{ $expense->expense_number }} · <span class="status-badge {{ $expense->status }}">{{ $expense->status }}</span></p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="form-card" style="max-width:720px">
    <div class="detail-list">
        <div class="dl-label">Amount</div><div class="dl-value" style="font-size:18px;font-weight:800">{{ money($expense->amount) }}</div>
        <div class="dl-label">Category</div><div class="dl-value">{{ $expense->category }}</div>
        <div class="dl-label">Account</div><div class="dl-value">{{ $expense->account?->name ?? '—' }}</div>
        <div class="dl-label">Vendor</div><div class="dl-value">{{ $expense->vendor ?: '—' }}</div>
        <div class="dl-label">Method</div><div class="dl-value">{{ ucfirst(str_replace('_',' ',$expense->payment_method)) }}</div>
        <div class="dl-label">Date</div><div class="dl-value">{{ $expense->expense_date?->format('M d, Y') }}</div>
        <div class="dl-label">Recorded By</div><div class="dl-value">{{ $expense->recorder?->name ?? '—' }}</div>
        <div class="dl-label">Description</div><div class="dl-value">{{ $expense->description ?: '—' }}</div>
        <div class="dl-label">Attachment</div>
        <div class="dl-value">
            @if($expense->receipt_url)
                @if($expense->receipt_is_image)
                    <a href="{{ $expense->receipt_url }}" target="_blank" rel="noopener">
                        <img src="{{ $expense->receipt_url }}" alt="Receipt" style="max-width:280px;max-height:200px;border-radius:10px;border:1px solid var(--border);object-fit:cover;display:block;margin-top:4px">
                    </a>
                @else
                    <a href="{{ $expense->receipt_url }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Download / View file</a>
                @endif
            @else
                —
            @endif
        </div>
    </div>
</div>
@endsection
