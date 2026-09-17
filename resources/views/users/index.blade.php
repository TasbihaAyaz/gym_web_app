@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Users</h1>
        <p>Manage staff accounts, roles, and access status</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add User
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Users</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Inactive</div><div class="value" style="color:#ef4444">{{ number_format($stats['inactive']) }}</div></div>
    <div class="mini-stat"><div class="label">Admins</div><div class="value" style="color:#2038e0">{{ number_format($stats['admins']) }}</div></div>
</div>

<form method="GET" action="{{ route('users.index') }}" class="filter-bar">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search name, email, phone...">
    </div>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach(['active','inactive'] as $st)
            <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
        @endforeach
    </select>
    <select name="role_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Roles</option>
        @foreach($roles as $role)
            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status','role_id']))
        <a href="{{ route('users.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($users->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <div class="cell-user">
                                    <div class="avatar-initials">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                                    <div>
                                        <span class="name">{{ $user->name }}</span>
                                        <span class="sub">{{ $user->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td>
                                @if($user->role)
                                    <span class="code-pill">{{ $user->role->name }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="status-badge {{ $user->status }}">{{ $user->status }}</span></td>
                            <td>{{ $user->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('users.show', $user) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('users.edit', $user) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon danger" title="Delete">
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
        @include('partials.pagination', ['paginator' => $users])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <h3>No users found</h3>
            <p>Create a staff account or adjust the filters.</p>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Add User</a>
        </div>
    @endif
</div>
@endsection
