@extends('layouts.app')

@section('title', 'Add Plan')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Plan</h1>
        <p>Create a membership package</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('plans.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('plans.form', ['plan' => null])
@endsection
