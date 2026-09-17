@extends('layouts.app')

@section('title', 'Edit Plan')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Plan</h1>
        <p>Update package pricing and features</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('plans.show', $plan) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('plans.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('plans.form', ['plan' => $plan])
@endsection
