@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit User</h1>
        <p>Update account details for {{ $user->name }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('users.show', $user) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('users.form')
@endsection
