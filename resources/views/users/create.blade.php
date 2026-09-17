@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add User</h1>
        <p>Create a staff login and assign a role</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('users.form', ['user' => null])
@endsection
