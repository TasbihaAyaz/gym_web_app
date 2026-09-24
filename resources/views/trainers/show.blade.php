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

<div class="form-card" style="margin-top:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px">
        <h3 style="font-size:15px;margin:0">Assigned Members</h3>
        <span style="font-size:12px;color:var(--text-mute)">{{ $trainer->members->count() }} member{{ $trainer->members->count() === 1 ? '' : 's' }}</span>
    </div>
    @if($trainer->members->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Bio ID</th>
                        <th>Phone</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trainer->members as $member)
                        <tr>
                            <td>
                                <div class="cell-user">
                                    @if($member->avatar_url)
                                        <img src="{{ $member->avatar_url }}" alt="" class="avatar-img">
                                    @else
                                        <div class="avatar-initials">{{ $member->initials }}</div>
                                    @endif
                                    <div>
                                        <span class="name">{{ $member->full_name }}</span>
                                        <span class="sub">{{ $member->email ?? 'No email' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="code-pill">{{ $member->device_user_id ?: '—' }}</span></td>
                            <td>{{ $member->phone ?? '—' }}</td>
                            <td>{{ $member->activeSubscription?->plan?->name ?? '—' }}</td>
                            <td><span class="status-badge {{ $member->status }}">{{ $member->status }}</span></td>
                            <td>
                                <a href="{{ route('members.show', $member) }}" class="btn-icon" title="View">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p style="color:var(--text-dim);font-size:13px">No members assigned to this trainer.</p>
    @endif
</div>
@endsection
