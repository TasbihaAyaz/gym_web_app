@extends('layouts.app')

@section('title', 'Add Trainer')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Trainer</h1>
        <p>Create a new coach profile</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('trainers.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('trainers.form', ['trainer' => null])
@endsection
