@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Attendance</h1>
        <p>{{ !empty($isStaffView) ? 'Staff & trainer check-ins, late and off for the day' : 'Track daily member check-ins' }}</p>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Today</div><div class="value">{{ number_format($stats['today']) }}</div></div>
    <div class="mini-stat"><div class="label">This Week</div><div class="value" style="color:#2038e0">{{ number_format($stats['week']) }}</div></div>
    <div class="mini-stat"><div class="label">This Month</div><div class="value" style="color:#3b82f6">{{ number_format($stats['month']) }}</div></div>
    <div class="mini-stat"><div class="label">Members Today</div><div class="value" style="color:#22c55e">{{ number_format($stats['members_today']) }}</div></div>
</div>

<form method="GET" action="{{ route('attendance.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ !empty($isStaffView) ? 'Search staff…' : 'Search member…' }}">
    </div>
    <input type="date" name="date" class="form-control" style="max-width:180px" value="{{ $date }}" onchange="this.form.submit()">
    <select name="person" class="form-select" style="max-width:180px" onchange="this.form.submit()">
        <option value="" @selected(request('person', '') === '')>Gym Members</option>
        <option value="staff" @selected(request('person') === 'staff')>Staff / Trainers</option>
    </select>
    @unless(!empty($isStaffView))
    <select name="method" class="form-select" onchange="this.form.submit()">
        <option value="">All Methods</option>
        @foreach(['manual','biometric','app'] as $m)
            <option value="{{ $m }}" @selected(request('method')===$m)>{{ ucfirst($m) }}</option>
        @endforeach
    </select>
    @endunless
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','date','method','person']))
        <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

@if(!empty($isStaffView))
    <p style="margin:-4px 0 14px;font-size:12.5px;color:var(--text-mute)">
        Late after <strong>{{ $staffCheckinTime ?? '10:00' }}</strong>.
        Mark staff on Edit Member → <em>Staff / Trainer</em> checkbox. Off = no biometric/manual check-in that day.
    </p>
@endif

<div class="table-card">
    @if(!empty($isStaffView))
        @if(($staffRows ?? collect())->count())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Bio ID</th>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Status</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        @include('attendance.partials.staff-rows', ['staffRows' => $staffRows, 'date' => $date])
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <p>No staff members marked yet.</p>
                <p style="font-size:13px;color:var(--text-mute);margin-top:6px">Open a trainer/staff person in Members → Edit and enable <strong>Staff / Trainer</strong>.</p>
            </div>
        @endif
    @elseif($attendances && $attendances->count())
        <div class="table-wrap">
            <table class="data-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Package</th>
                            <th>Monthly Fee</th>
                            <th>Fee Expiry</th>
                            <th>Trainer</th>
                        </tr>
                    </thead>
                <tbody id="attendance-rows">
                    @include('attendance.partials.rows', ['attendances' => $attendances])
                </tbody>
            </table>
        </div>

        @php
            $scrollParams = [
                'search' => request('search'),
                'date' => $date,
                'method' => request('method'),
                'person' => request('person'),
            ];
        @endphp
        <div
            class="infinite-scroll"
            data-infinite-scroll
            data-url="{{ route('attendance.index') }}"
            data-target="#attendance-rows"
            data-next-page="{{ $attendances->hasMorePages() ? $attendances->currentPage() + 1 : '' }}"
            data-params="{{ json_encode($scrollParams) }}"
        >
            <div class="infinite-spinner" hidden>
                <span class="spinner"></span>
                <span>Loading more…</span>
            </div>
            <div class="infinite-end" hidden>All {{ number_format($attendances->total()) }} records loaded</div>
        </div>
    @else
        <div class="empty-state">
            <p>No attendance records found.</p>
        </div>
    @endif
</div>
@endsection
