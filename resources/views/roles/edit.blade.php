@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Role</h1>
        <p>Update {{ $role->name }} and its permissions</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('roles.show', $role) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('roles.form')
@endsection
