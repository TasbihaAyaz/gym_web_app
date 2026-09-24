@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
<div class="form-card" style="max-width:560px;margin:48px auto;text-align:center;padding:36px 28px">
    <h1 style="font-size:22px;margin-bottom:8px">Access denied</h1>
    <p style="color:var(--text-mute);margin-bottom:22px">
        {{ isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'You do not have permission to view this page for your role.' }}
    </p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to dashboard</a>
</div>
@endsection
