@extends('layouts.app')

@section('title', 'Plans & Packages')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Plans &amp; Packages</h1>
        <p>Membership tiers, pricing, and package features</p>
    </div>
    <div class="toolbar-actions">
        @perm('plans.create')
        <a href="{{ route('plans.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Plan
        </a>
        @endperm
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Plans</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Basic</div><div class="value" style="color:#f59e0b">{{ number_format($stats['basic']) }}</div></div>
    <div class="mini-stat"><div class="label">Premium</div><div class="value" style="color:#2038e0">{{ number_format($stats['premium']) }}</div></div>
</div>

<form method="GET" action="{{ route('plans.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search plans...">
    </div>
    <select name="tier" class="form-select" onchange="this.form.submit()">
        <option value="">All Tiers</option>
        @foreach(['basic','standard','premium'] as $tier)
            <option value="{{ $tier }}" @selected(request('tier')===$tier)>{{ ucfirst($tier) }}</option>
        @endforeach
    </select>
    <select name="is_active" class="form-select" onchange="this.form.submit()">
        <option value="">All Visibility</option>
        <option value="1" @selected(request('is_active')==='1')>Active only</option>
        <option value="0" @selected(request('is_active')==='0')>Inactive only</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','tier','is_active']))
        <a href="{{ route('plans.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

@if($plans->count())
    <div class="plans-grid">
        @foreach($plans as $plan)
            <div class="plan-card {{ $plan->tier === 'premium' ? 'featured' : '' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div class="plan-tier">{{ $plan->tier }}</div>
                    <span class="status-badge {{ $plan->is_active ? 'active' : 'inactive' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <h3>{{ $plan->name }}</h3>
                <div class="plan-price">{{ money($plan->price) }} <span>/ {{ $plan->duration_days }} days</span></div>
                <p class="plan-desc">{{ $plan->description ?: 'Membership package' }}</p>
                <ul class="plan-features">
                    @forelse(($plan->features ?? []) as $feature)
                        <li>
                            <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $feature }}
                        </li>
                    @empty
                        <li style="color:var(--text-mute)">No features listed</li>
                    @endforelse
                </ul>
                <div style="font-size:11.5px;color:var(--text-mute);margin-bottom:14px">
                    {{ $plan->subscriptions_count }} subscription{{ $plan->subscriptions_count === 1 ? '' : 's' }}
                </div>
                <div class="plan-card-actions">
                    <a href="{{ route('plans.show', $plan) }}" class="btn btn-secondary btn-sm" style="flex:1">View</a>
                    @perm('plans.edit')
                    <a href="{{ route('plans.edit', $plan) }}" class="btn btn-primary btn-sm" style="flex:1">Edit</a>
                    @endperm
                    @perm('plans.delete')
                    <form action="{{ route('plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('Delete this plan?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                    @endperm
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px" class="table-card">
        @include('partials.pagination', ['paginator' => $plans])
    </div>
@else
    <div class="table-card">
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
            <h3>No plans found</h3>
            <p>Create membership packages for your members.</p>
            @perm('plans.create')
            <a href="{{ route('plans.create') }}" class="btn btn-primary">Add Plan</a>
            @endperm
        </div>
    </div>
@endif
@endsection
