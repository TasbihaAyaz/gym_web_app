@extends('layouts.app')

@section('title', $trainer->full_name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $trainer->full_name }}</h1>
        <p>Trainer profile · <span class="code-pill">{{ $trainer->trainer_code }}</span></p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('trainers.edit', $trainer) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('trainers.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div class="detail-hero">
            @if($trainer->avatar_url)
                <img src="{{ $trainer->avatar_url }}" alt="" class="avatar-lg">
            @else
                <div class="avatar-lg initials">{{ $trainer->initials }}</div>
            @endif
            <div>
                <h2 style="font-size:20px;margin-bottom:6px">{{ $trainer->full_name }}</h2>
                <span class="status-badge {{ $trainer->status }}">{{ $trainer->status }}</span>
            </div>
        </div>
        <div class="detail-list">
            <div class="dl-label">Email</div><div class="dl-value">{{ $trainer->email ?? '—' }}</div>
            <div class="dl-label">Phone</div><div class="dl-value">{{ $trainer->phone ?? '—' }}</div>
            <div class="dl-label">Specialization</div><div class="dl-value">{{ $trainer->specialization ?? '—' }}</div>
            <div class="dl-label">Hourly Rate</div><div class="dl-value">{{ $trainer->hourly_rate !== null ? money($trainer->hourly_rate) : '—' }}</div>
            <div class="dl-label">Hire Date</div><div class="dl-value">{{ $trainer->hire_date?->format('M d, Y') ?? '—' }}</div>
            <div class="dl-label">Bio</div><div class="dl-value">{{ $trainer->bio ?? '—' }}</div>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Assigned Classes</h3>
        @forelse($trainer->gymClasses as $class)
            <div style="padding:12px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px">
                <div>
                    <div style="font-weight:600">{{ $class->name }}</div>
                    <div style="font-size:12px;color:var(--text-mute)">{{ $class->duration_minutes }} min · Cap {{ $class->capacity }}</div>
                </div>
                <span class="status-badge {{ $class->status }}">{{ $class->status }}</span>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No classes assigned.</p>
        @endforelse
    </div>
</div>
@endsection
