@extends('layouts.app')

@section('title', $role->name)

@section('content')
<div class="page-toolbar">
    <div>
        <h1>{{ $role->name }}</h1>
        <p>Role details · <span class="code-pill">{{ $role->slug }}</span></p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('roles.edit', $role) }}" class="btn btn-primary">Edit</a>
        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

<div class="detail-grid">
    <div class="form-card">
        <div class="detail-hero">
            <div class="avatar-lg initials role-av">{{ strtoupper(substr($role->name, 0, 2)) }}</div>
            <div>
                <h2 style="font-size:20px;margin-bottom:6px">{{ $role->name }}</h2>
                <span class="status-badge {{ $role->is_active ? 'active' : 'inactive' }}">
                    {{ $role->is_active ? 'active' : 'inactive' }}
                </span>
            </div>
        </div>
        <div class="detail-list">
            <div class="dl-label">Slug</div><div class="dl-value">{{ $role->slug }}</div>
            <div class="dl-label">Description</div><div class="dl-value">{{ $role->description ?? '—' }}</div>
            <div class="dl-label">Permissions</div><div class="dl-value">{{ $role->permissions->count() }}</div>
            <div class="dl-label">Assigned Users</div><div class="dl-value">{{ $role->users->count() }}</div>
        </div>

        <h3 style="margin:22px 0 12px;font-size:15px">Users with this role</h3>
        @forelse($role->users as $user)
            <div style="padding:10px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px">
                <div>
                    <div style="font-weight:600">{{ $user->name }}</div>
                    <div style="font-size:12px;color:var(--text-mute)">{{ $user->email }}</div>
                </div>
                <a href="{{ route('users.show', $user) }}" class="btn btn-ghost btn-sm">View</a>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No users assigned.</p>
        @endforelse
    </div>

    <div class="form-card">
        <h3 style="margin-bottom:14px;font-size:15px">Permission Matrix</h3>
        @forelse($permissionGroups as $module => $perms)
            <div class="perm-module-block">
                <div class="perm-module-title">{{ ucfirst(str_replace('_', ' ', $module)) }}</div>
                <div class="perm-chip-row">
                    @foreach($perms as $perm)
                        <span class="perm-chip">{{ $perm->name }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No permissions assigned.</p>
        @endforelse
    </div>
</div>
@endsection
