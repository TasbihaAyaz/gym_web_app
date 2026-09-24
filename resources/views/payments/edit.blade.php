@extends('layouts.app')

@section('title', 'Edit Payment')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Payment</h1>
        <p>{{ $payment->payment_number }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('payments.show', $payment) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">Back to list</a>
        @perm('payments.delete')
        <form action="{{ route('payments.destroy', $payment) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this payment? This will reverse the amount from the linked account if it was completed.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete Payment</button>
        </form>
        @endperm
    </div>
</div>
@include('payments.form', ['payment' => $payment, 'members' => $members, 'accounts' => $accounts, 'plans' => $plans ?? collect()])
@endsection
