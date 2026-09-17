@extends('layouts.app')

@section('title', 'Edit Attendance')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Attendance</h1>
        <p>{{ $attendance->member?->full_name }} · {{ $attendance->attendance_date?->format('M d, Y') }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('attendance.show', $attendance) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('attendance.form', ['attendance' => $attendance, 'members' => $members])
@endsection
