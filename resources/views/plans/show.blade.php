@extends('layouts.app')

@section('title', $plan->name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $plan->name }}</h1>
        <p>Plan details · {{ $plan->subscriptions_count }} subscriptions</p>
    </div>
    <div class="toolbar-actions">
        @perm('plans.edit')
        <a href="{{ route('plans.edit', $plan) }}" class="btn btn-primary">Edit</a>
        @endperm
        <a href="{{ route('plans.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div style="max-width:520px">
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
    </div>
</div>
@endsection
