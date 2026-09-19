@extends('layouts.app')

@section('title', 'Attendance Details')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $attendance->member?->full_name ?? 'Attendance' }}</h1>
        <p>{{ $attendance->attendance_date?->format('l, M d, Y') }}</p>
    </div>
    <div class="toolbar-actions">
        @perm('attendance.edit')
        <a href="{{ route('attendance.edit', $attendance) }}" class="btn btn-primary">Edit</a>
        @endperm
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="form-card" style="max-width:640px">
    <div class="detail-list">
        <div class="dl-label">Member</div><div class="dl-value">{{ $attendance->member?->full_name }} ({{ $attendance->member?->member_code }})</div>
        <div class="dl-label">Date</div><div class="dl-value">{{ $attendance->attendance_date?->format('M d, Y') }}</div>
        <div class="dl-label">Check In</div><div class="dl-value">{{ $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('h:i A') : '—' }}</div>
        <div class="dl-label">Check Out</div><div class="dl-value">{{ $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('h:i A') : '—' }}</div>
        <div class="dl-label">Method</div><div class="dl-value"><span class="status-badge {{ $attendance->method }}">{{ $attendance->method }}</span></div>
        <div class="dl-label">Notes</div><div class="dl-value">{{ $attendance->notes ?: '—' }}</div>
    </div>
</div>
@endsection
