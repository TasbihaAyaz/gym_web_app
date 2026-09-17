@extends('layouts.app')

@section('title', 'Add Class')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Class</h1>
        <p>Create a new fitness class</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('classes.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('classes.form', ['class' => null, 'trainers' => $trainers])
@endsection
