@extends('layouts.app')

@section('title', 'Edit Class')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Class</h1>
        <p>Update class details</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('classes.show', $class) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('classes.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('classes.form', ['class' => $class, 'trainers' => $trainers])
@endsection
