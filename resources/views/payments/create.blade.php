@extends('layouts.app')

@section('title', 'Collect Fee Payment')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Collect Fee Payment</h1>
        <p>Record membership fee for a member</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('payments.form', ['payment' => null, 'members' => $members, 'accounts' => $accounts, 'plans' => $plans])
@endsection
