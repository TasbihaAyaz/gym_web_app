@extends('layouts.app')

@section('title', 'Edit Member')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Member</h1>
        <p>Update member profile and status</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('members.show', $member) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('members.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>

@include('members.form', ['member' => $member, 'plans' => $plans, 'subscription' => $subscription, 'trainers' => $trainers])
@endsection
