@extends('layouts.app')

@section('title', 'Add Role')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Role</h1>
        <p>Create a role and assign module permissions</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('roles.form', ['role' => null, 'selected' => []])
@endsection
