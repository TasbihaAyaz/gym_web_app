@extends('layouts.app')

@section('title', 'Check In')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Check In Member</h1>
        <p>Record a new attendance entry</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('attendance.form', ['attendance' => null, 'members' => $members])
@endsection
