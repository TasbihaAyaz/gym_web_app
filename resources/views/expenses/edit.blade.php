@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Edit Expense</h1>
        <p>{{ $expense->expense_number }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('expenses.show', $expense) }}" class="btn btn-ghost">View</a>
        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Back to list</a>
    </div>
</div>
@include('expenses.form', ['expense' => $expense, 'accounts' => $accounts, 'categories' => $categories])
@endsection
