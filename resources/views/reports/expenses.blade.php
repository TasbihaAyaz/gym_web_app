@extends('layouts.app')

@section('title', 'Reports · Expenses')

@section('content')
@include('reports._toolbar')

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Expense Entries</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Total Amount</div><div class="value" style="color:#ef4444">{{ money($stats['amount']) }}</div></div>
    <div class="mini-stat"><div class="label">Approved</div><div class="value" style="color:#22c55e">{{ money($stats['approved']) }}</div></div>
    <div class="mini-stat"><div class="label">Pending</div><div class="value" style="color:#f59e0b">{{ money($stats['pending']) }}</div></div>
</div>

@if($byCategory->isNotEmpty())
<div class="form-card" style="margin-bottom:16px">
    <h3 style="font-size:14.5px;margin-bottom:12px">By Category</h3>
    <div class="report-grid-3" style="gap:10px">
        @foreach($byCategory as $category => $row)
            <div class="report-metric" style="border:1px solid var(--border);border-radius:10px;padding:12px">
                <div>
                    <div style="font-weight:600">{{ $category ?: 'Uncategorized' }}</div>
                    <div style="font-size:12px;color:var(--text-mute);margin-top:2px">{{ $row['count'] }} entries · approved {{ money($row['approved']) }}</div>
                </div>
                <span class="val" style="color:#ef4444">{{ money($row['amount']) }}</span>
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="form-card" style="padding:0;overflow:hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense #</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Vendor</th>
                    <th>Method</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_date?->format('M d, Y') }}</td>
                        <td><span class="code-pill">{{ $expense->expense_number }}</span></td>
                        <td>
                            <a href="{{ route('expenses.show', $expense) }}" style="font-weight:600;color:inherit">{{ $expense->title }}</a>
                            @if($expense->description)
                                <div style="font-size:11.5px;color:var(--text-mute);margin-top:2px;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $expense->description }}</div>
                            @endif
                        </td>
                        <td>{{ $expense->category ?: '—' }}</td>
                        <td>{{ $expense->vendor ?: '—' }}</td>
                        <td>{{ $expense->payment_method ? ucfirst(str_replace('_',' ',$expense->payment_method)) : '—' }}</td>
                        <td>{{ $expense->account?->name ?? '—' }}</td>
                        <td><span class="status-badge {{ $expense->status }}">{{ $expense->status }}</span></td>
                        <td style="font-weight:700;color:#ef4444">{{ money($expense->amount) }}</td>
                        <td style="font-size:12.5px">{{ $expense->recorder?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;padding:28px;color:var(--text-dim)">No expenses in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
