@extends('layouts.app')

@section('title', 'Edit Account')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Account</h1>
        <p>{{ $account->code }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('accounts.show', $account) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('accounts.form', ['account' => $account])
@endsection
