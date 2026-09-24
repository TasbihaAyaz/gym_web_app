@extends('layouts.app')

@section('title', $class->name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $class->name }}</h1>
        <p>Class details and enrollments</p>
    </div>
    <div class="toolbar-actions">
        @perm('classes.edit')
        <a href="{{ route('classes.edit', $class) }}" class="btn btn-primary">Edit</a>
        @endperm
        <a href="{{ route('classes.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px">
            <div>
                <h2 style="font-size:20px;margin-bottom:8px">{{ $class->name }}</h2>
                <div class="class-meta">
                    <span>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
                        {{ $class->trainer?->full_name ?? 'Unassigned' }}
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        {{ $class->duration_minutes }} min
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        {{ $class->enrollments->count() }} / {{ $class->capacity }}
                    </span>
                </div>
            </div>
            <span class="status-badge {{ $class->status }}">{{ $class->status }}</span>
        </div>
        <div class="detail-list">
            <div class="dl-label">Description</div>
            <div class="dl-value">{{ $class->description ?: '—' }}</div>
            <div class="dl-label">Capacity</div>
            <div class="dl-value">{{ $class->capacity }} members</div>
            <div class="dl-label">Duration</div>
            <div class="dl-value">{{ $class->duration_minutes }} minutes</div>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Recent Enrollments</h3>
        @forelse($class->enrollments->take(8) as $enrollment)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px">
                <div>
                    <div style="font-weight:600;font-size:13px">{{ $enrollment->member?->full_name ?? 'Member' }}</div>
                    <div style="font-size:11.5px;color:var(--text-mute)">{{ $enrollment->enrolled_at?->format('M d, Y') }}</div>
                </div>
                <span class="status-badge {{ $enrollment->status === 'enrolled' ? 'active' : $enrollment->status }}">{{ $enrollment->status }}</span>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No enrollments yet.</p>
        @endforelse
    </div>
</div>
@endsection
