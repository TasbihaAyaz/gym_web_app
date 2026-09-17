@extends('layouts.app')

@section('title', 'Edit Trainer')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Trainer</h1>
        <p>Update coach details</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('trainers.show', $trainer) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('trainers.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('trainers.form', ['trainer' => $trainer])
@endsection
