@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Attendance</h1>
        <p>Track daily member check-ins</p>
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
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search member...">
    </div>
    <input type="date" name="date" class="form-control" style="max-width:180px" value="{{ $date }}" onchange="this.form.submit()">
    <select name="method" class="form-select" onchange="this.form.submit()">
        <option value="">All Methods</option>
        @foreach(['manual','biometric','app'] as $m)
            <option value="{{ $m }}" @selected(request('method')===$m)>{{ ucfirst($m) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','date','method']))
        <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($attendances->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Package</th>
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
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M9 11.5 11 13.5 15 9.5"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4"/></svg></div>
            <h3>No attendance records</h3>
            <p>Check-ins from the biometric device will appear here.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/infinite-scroll.js') }}?v=1"></script>
@endpush
