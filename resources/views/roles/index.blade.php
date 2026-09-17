@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Roles & Permissions</h1>
        <p>Define access levels and module permissions for staff</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('roles.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Role
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Roles</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Permissions</div><div class="value" style="color:#3b82f6">{{ number_format($stats['permissions']) }}</div></div>
    <div class="mini-stat"><div class="label">In Use</div><div class="value" style="color:#2038e0">{{ number_format($stats['assigned']) }}</div></div>
</div>

<form method="GET" action="{{ route('roles.index') }}" class="filter-bar">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search role name or description...">
    </div>
    <select name="is_active" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="1" @selected(request('is_active') === '1')>Active</option>
        <option value="0" @selected(request('is_active') === '0')>Inactive</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','is_active']))
        <a href="{{ route('roles.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($roles->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Slug</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td>
                                <div class="cell-user">
                                    <div class="avatar-initials role-av">{{ strtoupper(substr($role->name, 0, 2)) }}</div>
                                    <div>
                                        <span class="name">{{ $role->name }}</span>
                                        <span class="sub">{{ Str::limit($role->description ?? 'No description', 48) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="code-pill">{{ $role->slug }}</span></td>
                            <td>{{ number_format($role->users_count) }}</td>
                            <td>{{ number_format($role->permissions_count) }}</td>
                            <td>
                                <span class="status-badge {{ $role->is_active ? 'active' : 'inactive' }}">
                                    {{ $role->is_active ? 'active' : 'inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('roles.show', $role) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('roles.edit', $role) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form action="{{ route('roles.destroy', $role) }}" method="POST" onsubmit="return confirm('Delete this role?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon danger" title="Delete" @disabled($role->slug === 'admin')>
                                            <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $roles])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3>No roles found</h3>
            <p>Create a role or adjust the filters.</p>
            <a href="{{ route('roles.create') }}" class="btn btn-primary">Add Role</a>
        </div>
    @endif
</div>
@endsection
