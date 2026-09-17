@extends('layouts.app')

@section('title', 'Add Account')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Add Account</h1>
        <p>Create a new financial ledger account</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('accounts.form', ['account' => null])
@endsection
