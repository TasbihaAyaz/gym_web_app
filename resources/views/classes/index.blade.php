@extends('layouts.app')

@section('title', 'Classes')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Classes</h1>
        <p>Schedule and manage fitness classes</p>
    </div>
    <div class="toolbar-actions">
        @perm('classes.create')
        <a href="{{ route('classes.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Class
        </a>
        @endperm
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Classes</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Inactive</div><div class="value" style="color:#ef4444">{{ number_format($stats['inactive']) }}</div></div>
    <div class="mini-stat"><div class="label">Enrollments</div><div class="value" style="color:#2038e0">{{ number_format($stats['enrollments']) }}</div></div>
</div>

<form method="GET" action="{{ route('classes.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search class name...">
    </div>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active" @selected(request('status')==='active')>Active</option>
        <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
    </select>
    <select name="trainer_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Trainers</option>
        @foreach($trainers as $trainer)
            <option value="{{ $trainer->id }}" @selected(request('trainer_id') == $trainer->id)>{{ $trainer->full_name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status','trainer_id']))
        <a href="{{ route('classes.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($classes->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Trainer</th>
                        <th>Duration</th>
                        <th>Capacity</th>
                        <th>Enrolled</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($classes as $class)
                        @php
                            $pct = $class->capacity > 0 ? min(100, round(($class->enrollments_count / $class->capacity) * 100)) : 0;
                        @endphp
                        <tr>
                            <td>
                                <div class="cell-user">
                                    @if($class->image_url || $class->trainer?->avatar_url)
                                        <img src="{{ $class->image_url ?: $class->trainer->avatar_url }}" alt="" class="avatar-img square">
                                    @else
                                        <div class="avatar-initials">{{ strtoupper(substr($class->name, 0, 2)) }}</div>
                                    @endif
                                    <div>
                                        <div class="name" style="font-weight:600">{{ $class->name }}</div>
                                        <div class="sub" style="font-size:11.5px;color:var(--text-mute);margin-top:2px">{{ Str::limit($class->description, 48) ?: 'No description' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $class->trainer?->full_name ?? 'Unassigned' }}</td>
                            <td>{{ $class->duration_minutes }} min</td>
                            <td>
                                <div style="min-width:110px">
                                    <div style="font-size:12px;margin-bottom:4px">{{ $pct }}% · {{ $class->capacity }} seats</div>
                                    <div class="cap-bar"><div class="cap-fill purple" style="width:{{ $pct }}%"></div></div>
                                </div>
                            </td>
                            <td>{{ $class->enrollments_count }}</td>
                            <td><span class="status-badge {{ $class->status }}">{{ $class->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('classes.show', $class) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @perm('classes.edit')
                                    <a href="{{ route('classes.edit', $class) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    @endperm
                                    @perm('classes.delete')
                                    <form action="{{ route('classes.destroy', $class) }}" method="POST" onsubmit="return confirm('Delete this class?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon danger" title="Delete">
                                            <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                        </button>
                                    </form>
                                    @endperm
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $classes])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
            <h3>No classes found</h3>
            <p>Create classes and assign trainers.</p>
            @perm('classes.create')
            <a href="{{ route('classes.create') }}" class="btn btn-primary">Add Class</a>
            @endperm
        </div>
    @endif
</div>
@endsection
