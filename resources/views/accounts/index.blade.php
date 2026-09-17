@extends('layouts.app')

@section('title', 'Accounts')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Accounts</h1>
        <p>Manage cash, bank, income and expense ledgers</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Account
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Accounts</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Asset Balance</div><div class="value" style="color:#3b82f6">{{ money($stats['assets']) }}</div></div>
    <div class="mini-stat"><div class="label">Income Balance</div><div class="value" style="color:#2038e0">{{ money($stats['income']) }}</div></div>
</div>

<form method="GET" action="{{ route('accounts.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search name or code...">
    </div>
    <select name="type" class="form-select" onchange="this.form.submit()">
        <option value="">All Types</option>
        @foreach(['asset','liability','income','expense','equity'] as $type)
            <option value="{{ $type }}" @selected(request('type')===$type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
    <select name="is_active" class="form-select" onchange="this.form.submit()">
        <option value="">All Visibility</option>
        <option value="1" @selected(request('is_active')==='1')>Active only</option>
        <option value="0" @selected(request('is_active')==='0')>Inactive only</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','type','is_active']))
        <a href="{{ route('accounts.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

@if($accounts->count())
    <div class="accounts-grid" style="margin-bottom:16px">
        @foreach($accounts as $account)
            <div class="account-card">
                <div class="top">
                    <div>
                        <div class="meta">{{ $account->code }}</div>
                        <h3>{{ $account->name }}</h3>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                        <span class="status-badge {{ $account->type }}">{{ $account->type }}</span>
                        <span class="status-badge {{ $account->is_active ? 'active' : 'inactive' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
                <div class="balance">{{ money($account->current_balance) }}</div>
                <div class="meta">Opening: {{ money($account->opening_balance) }}</div>
                <p style="font-size:12.5px;color:var(--text-dim);margin:10px 0 14px;min-height:36px">{{ $account->description ?: 'No description' }}</p>
                <div style="display:flex;gap:8px">
                    <a href="{{ route('accounts.show', $account) }}" class="btn btn-secondary btn-sm" style="flex:1">View</a>
                    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-primary btn-sm" style="flex:1">Edit</a>
                    <form action="{{ route('accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Delete this account?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <div class="table-card">
        @include('partials.pagination', ['paginator' => $accounts])
    </div>
@else
    <div class="table-card">
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M3 10h18"/><path d="m5 6 7-3 7 3"/><path d="M4 10v11M20 10v11"/></svg></div>
            <h3>No accounts found</h3>
            <p>Create cash, bank, and ledger accounts.</p>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add Account</a>
        </div>
    </div>
@endif
@endsection
