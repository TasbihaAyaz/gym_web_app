@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $user->name }}</h1>
        <p>Staff account · {{ $user->email }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div class="detail-hero">
            <div class="avatar-lg initials">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
            <div>
                <h2 style="font-size:20px;margin-bottom:6px">{{ $user->name }}</h2>
                <span class="status-badge {{ $user->status }}">{{ $user->status }}</span>
            </div>
        </div>
        <div class="detail-list">
            <div class="dl-label">Email</div><div class="dl-value">{{ $user->email }}</div>
            <div class="dl-label">Phone</div><div class="dl-value">{{ $user->phone ?? '—' }}</div>
            <div class="dl-label">Role</div><div class="dl-value">{{ $user->role?->name ?? '—' }}</div>
            <div class="dl-label">Joined</div><div class="dl-value">{{ $user->created_at?->format('M d, Y') ?? '—' }}</div>
            <div class="dl-label">Last Updated</div><div class="dl-value">{{ $user->updated_at?->format('M d, Y H:i') ?? '—' }}</div>
        </div>
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Role Permissions</h3>
        @if($user->role && $user->role->permissions->count())
            <div class="perm-chips">
                @foreach($user->role->permissions->groupBy('module') as $module => $perms)
                    <div class="perm-module-block">
                        <div class="perm-module-title">{{ ucfirst(str_replace('_', ' ', $module)) }}</div>
                        <div class="perm-chip-row">
                            @foreach($perms as $perm)
                                <span class="perm-chip">{{ $perm->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($user->role)
            <p style="color:var(--text-dim);font-size:13px">This role has no permissions assigned.</p>
        @else
            <p style="color:var(--text-dim);font-size:13px">No role assigned to this user.</p>
        @endif
    </div>
</div>
@endsection
