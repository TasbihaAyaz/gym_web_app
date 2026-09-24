@extends('layouts.app')

@section('title', 'Add Member')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Member</h1>
        <p>Register a new gym member</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('members.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>

@include('members.form', [
    'member' => null,
    'plans' => $plans,
    'subscription' => null,
    'trainers' => $trainers,
    'defaultAdmissionFee' => $defaultAdmissionFee ?? 0,
])
@endsection
