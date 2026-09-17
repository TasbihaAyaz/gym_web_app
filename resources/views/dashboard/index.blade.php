@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $trend = fn ($key) => $stats['trends'][$key] ?? 0;
    $trendClass = fn ($key) => ($trend($key) ?? 0) >= 0 ? 'up' : 'down';
    $trendAbs = fn ($key) => number_format(abs((float) $trend($key)), 1) . '%';
    $ms = $stats['membership_status'];
    $msTotal = max(1, (int) $stats['membership_total']);
    $bio = $stats['biometric'] ?? ['connected' => false, 'label' => 'Biometric offline', 'title' => ''];
@endphp

<section class="dash-hero dash-reveal">
    <div class="dash-hero-glow" aria-hidden="true"></div>
    <div class="dash-hero-main">
        <div class="dash-hero-brand">
            <img src="{{ asset('assets/img/logo.jpg') }}" alt="Fit Generation" class="dash-hero-logo">
            <div>
                <p class="dash-hero-kicker">Fit Generation · Gym Management</p>
                <h1>Welcome back, {{ $stats['welcome_name'] }}</h1>
                <p class="dash-hero-copy">Here's what's happening at your gym · {{ $stats['range_label'] }}</p>
            </div>
        </div>
        <div class="dash-hero-meta">
            <span
                id="ci-live-status"
                class="ci-live-dot {{ $bio['connected'] ? 'is-online' : 'is-offline' }}"
                title="{{ $bio['title'] }}"
            ><i></i> <span class="ci-live-label">{{ $bio['label'] }}</span></span>
            <div class="page-header-actions">
                <div class="dropdown">
                    <button type="button" class="date-select" data-dropdown-toggle>
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <span>{{ $stats['range_label'] }}</span>
                        <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="dropdown-menu">
                        <a href="{{ route('dashboard', ['range' => 'week', 'revenue_period' => $stats['revenue_period'], 'bar_period' => $stats['bar_period']]) }}" class="{{ $stats['range'] === 'week' ? 'active' : '' }}">This Week</a>
                        <a href="{{ route('dashboard', ['range' => 'month', 'revenue_period' => $stats['revenue_period'], 'bar_period' => $stats['bar_period']]) }}" class="{{ $stats['range'] === 'month' ? 'active' : '' }}">This Month</a>
                        <a href="{{ route('dashboard', ['range' => 'year', 'revenue_period' => $stats['revenue_period'], 'bar_period' => $stats['bar_period']]) }}" class="{{ $stats['range'] === 'year' ? 'active' : '' }}">This Year</a>
                    </div>
                </div>
                <div class="dropdown">
                    <button type="button" class="filter-btn" data-dropdown-toggle>
                        <svg viewBox="0 0 24 24"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                        Shortcuts
                    </button>
                    <div class="dropdown-menu">
                        <a href="{{ route('members.index', ['status' => 'active']) }}">Active Members</a>
                        <a href="{{ route('payments.index') }}">Fee Payments</a>
                        <a href="{{ route('attendance.index') }}">Today's Attendance</a>
                        <a href="{{ route('reports.index') }}">Full Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats-row dash-stats dash-reveal">
    <a class="stat-card" href="{{ route('members.index') }}">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Members</span>
            <div class="stat-value-row">
                <span class="stat-value">{{ number_format($stats['total_members']) }}</span>
                <span class="stat-trend {{ $trendClass('total_members') }}"><svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>{{ $trendAbs('total_members') }}</span></span>
            </div>
            <span class="stat-sub">vs prior period</span>
        </div>
    </a>

    <a class="stat-card" href="{{ route('members.index', ['status' => 'active']) }}">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Active Members</span>
            <div class="stat-value-row">
                <span class="stat-value">{{ number_format($stats['active_members']) }}</span>
                <span class="stat-trend {{ $trendClass('active_members') }}"><svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>{{ $trendAbs('active_members') }}</span></span>
            </div>
            <span class="stat-sub">vs prior period</span>
        </div>
    </a>

    <a class="stat-card" href="{{ route('payments.index') }}">
        <div class="stat-icon violet">
            <svg viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Period Revenue</span>
            <div class="stat-value-row">
                <span class="stat-value">{{ money($stats['monthly_revenue'], 0) }}</span>
                <span class="stat-trend {{ $trendClass('monthly_revenue') }}"><svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>{{ $trendAbs('monthly_revenue') }}</span></span>
            </div>
            <span class="stat-sub">{{ ucfirst($stats['range']) }} completed payments</span>
        </div>
    </a>

    <a class="stat-card" href="{{ route('payments.create') }}">
        <div class="stat-icon orange">
            <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Due Payments</span>
            <div class="stat-value-row">
                <span class="stat-value">{{ money($stats['due_payments'], 0) }}</span>
                <span class="stat-trend {{ $trendClass('due_payments') }}"><svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>{{ $trendAbs('due_payments') }}</span></span>
            </div>
            <span class="stat-sub">Collect fee · {{ number_format($stats['due_count']) }} pending</span>
        </div>
    </a>

    <a class="stat-card" href="{{ route('members.index') }}">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">New Enrollments</span>
            <div class="stat-value-row">
                <span class="stat-value">{{ number_format($stats['new_enrollments']) }}</span>
                <span class="stat-trend {{ $trendClass('new_enrollments') }}"><svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>{{ $trendAbs('new_enrollments') }}</span></span>
            </div>
            <span class="stat-sub">joined this {{ $stats['range'] }}</span>
        </div>
    </a>
</section>

<section class="grid-row dash-reveal dash-reveal-2">
    <div class="card revenue-card">
        <div class="card-head">
            <h3>Members per Trainer</h3>
            <span class="card-note">Active members</span>
        </div>
        @php $topLoad = $stats['trainer_load']->max('members_count') ?: 1; @endphp
        <div class="trainer-load">
            @forelse($stats['trainer_load'] as $i => $trainer)
                <a class="tl-row {{ $i === 0 && $trainer->members_count > 0 ? 'is-top' : '' }}"
                   href="{{ route('members.index', ['search' => $trainer->full_name]) }}">
                    <span class="tl-rank">{{ $i + 1 }}</span>
                    <div class="tl-info">
                        <span class="tl-name">{{ $trainer->full_name }}</span>
                        <span class="tl-bar"><i style="width: {{ max(3, round($trainer->members_count / $topLoad * 100)) }}%"></i></span>
                    </div>
                    <span class="tl-count">{{ number_format($trainer->members_count) }}</span>
                </a>
            @empty
                <p class="empty-note">No trainers yet.</p>
            @endforelse

            @if($stats['self_training'] > 0)
                <div class="tl-row is-muted">
                    <span class="tl-rank">—</span>
                    <div class="tl-info">
                        <span class="tl-name">Self training</span>
                        <span class="tl-bar"><i style="width: {{ max(3, round($stats['self_training'] / $topLoad * 100)) }}%"></i></span>
                    </div>
                    <span class="tl-count">{{ number_format($stats['self_training']) }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="card membership-card">
        <div class="card-head">
            <h3>Membership Status</h3>
            <span class="card-note">{{ number_format($stats['membership_total']) }} members</span>
        </div>
        <div class="ms-body">
            <div class="donut-wrap">
                <canvas id="donutChart"></canvas>
                <div class="donut-center">
                    <span class="donut-total" id="donutTotal">{{ number_format($stats['membership_total']) }}</span>
                    <span class="donut-label">Total</span>
                </div>
            </div>
            <div class="donut-legend">
                @foreach([
                    'active' => ['green', 'Active'],
                    'leave' => ['orange', 'Leave'],
                    'pending' => ['blue', 'Pending'],
                    'cancelled' => ['red', 'Cancelled'],
                ] as $status => [$dot, $label])
                    @php $pct = ($ms[$status] / $msTotal) * 100; @endphp
                    <a class="legend-item ms-legend-{{ $dot }}" href="{{ route('members.index', ['status' => $status]) }}">
                        <span class="dot {{ $dot }}"></span>
                        <span class="legend-name">{{ $label }}</span>
                        <span class="legend-val">
                            <span class="donut-val">{{ number_format($ms[$status]) }}</span>
                            <span class="legend-pct">{{ number_format($pct, 1) }}%</span>
                        </span>
                        <span class="ms-bar" aria-hidden="true"><i style="width: {{ max(2, min(100, round($pct))) }}%"></i></span>
                    </a>
                @endforeach
            </div>
        </div>
        <a class="ghost-btn" href="{{ route('members.index') }}">View All Members</a>
    </div>

    <div class="card enrollments-card">
        <div class="card-head">
            <h3>Recent Enrollments</h3>
        </div>
        <div class="people-list">
            @forelse($stats['recent_members'] as $member)
                @php $tier = $member->activeSubscription?->plan?->tier ?? 'basic'; @endphp
                <a class="person-row" href="{{ route('members.show', $member) }}">
                    @if($member->avatar_url)
                        <img src="{{ $member->avatar_url }}" alt="{{ $member->full_name }}" />
                    @else
                        <div class="avatar-initials sm">{{ $member->initials }}</div>
                    @endif
                    <div class="person-info">
                        <span class="person-name">{{ $member->full_name }}</span>
                        <span class="person-sub">{{ $member->joined_at?->format('M j, Y') ?? '—' }}</span>
                    </div>
                    <span class="tag {{ $tier }}">{{ ucfirst($tier) }}</span>
                </a>
            @empty
                <p class="empty-inline">No members yet. <a href="{{ route('members.create') }}">Add one</a></p>
            @endforelse
        </div>
        <a class="ghost-btn" href="{{ route('members.index') }}">View All Enrollments</a>
    </div>
</section>

<section class="grid-row dash-reveal dash-reveal-3">
    <div class="card classes-card">
        <div class="card-head">
            <h3>Trainer Performance</h3>
            <a class="link" href="{{ route('trainers.index') }}">View All</a>
        </div>
        @if($stats['trainer_load']->isEmpty())
            <p class="empty-inline">No trainers yet. <a href="{{ route('trainers.create') }}">Add one</a></p>
        @else
            <div class="chart-wrap tall"><canvas id="trainerChart"></canvas></div>
        @endif
    </div>

    <div class="card payments-card">
        <div class="card-head">
            <h3>Pending Fee Collection</h3>
            <a class="link" href="{{ route('payments.create') }}">Collect Fee</a>
        </div>
        <div class="people-list">
            @forelse($stats['pending_fees'] as $member)
                @php
                    $sub = $member->activeSubscription;
                    $days = $sub?->end_date
                        ? (int) now()->startOfDay()->diffInDays($sub->end_date->copy()->startOfDay(), false)
                        : 0;
                    $dueLabel = $days < 0
                        ? abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue'
                        : 'Expired';
                    $dueAmt = (float) ($sub?->plan?->price ?? $sub?->amount_paid ?? 0);
                @endphp
                <a class="person-row" href="{{ route('payments.create', ['member_id' => $member->id]) }}">
                    @if($member->avatar_url)
                        <img src="{{ $member->avatar_url }}" alt="" />
                    @else
                        <div class="avatar-initials sm">{{ $member->initials }}</div>
                    @endif
                    <div class="person-info">
                        <span class="person-name">{{ $member->full_name }}</span>
                        <span class="person-sub">{{ $sub?->plan?->name ?? 'Fee due' }}</span>
                    </div>
                    <div class="pay-right">
                        <span class="pay-amt">{{ money($dueAmt) }}</span>
                        <span class="due-tag overdue">{{ $dueLabel }}</span>
                    </div>
                </a>
            @empty
                <p class="empty-inline">No pending fees. <a href="{{ route('payments.create') }}">Collect a payment</a></p>
            @endforelse
        </div>
    </div>
</section>

<section class="quick-actions dash-reveal">
    <div class="qa-text">
        <h3>Quick Actions</h3>
        <p>Jump into the most common gym tasks</p>
    </div>
    <div class="qa-buttons">
        <a href="{{ route('members.create') }}" class="qa-btn">
            <span class="qa-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></span>
            Add Member
        </a>
        <a href="{{ route('payments.create') }}" class="qa-btn">
            <span class="qa-icon"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg></span>
            Collect Fee
        </a>
        <a href="{{ route('expenses.create') }}" class="qa-btn">
            <span class="qa-icon"><svg viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg></span>
            Add Expense
        </a>
        <a href="{{ route('classes.create') }}" class="qa-btn">
            <span class="qa-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/></svg></span>
            Schedule Class
        </a>
        <a href="{{ route('reports.index') }}" class="qa-btn">
            <span class="qa-icon"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/></svg></span>
            View Reports
        </a>
    </div>
</section>
@endsection

@push('scripts')
<script>
window.dashboardData = @json($charts);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}?v=5"></script>
@endpush
