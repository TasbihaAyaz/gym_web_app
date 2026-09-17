@extends('layouts.app')

@section('title', 'Trainers')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Trainers</h1>
        <p>Manage coaches, specializations, and class assignments</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('trainers.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Trainer
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Trainers</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Inactive</div><div class="value" style="color:#ef4444">{{ number_format($stats['inactive']) }}</div></div>
    <div class="mini-stat"><div class="label">Classes Assigned</div><div class="value" style="color:#2038e0">{{ number_format($stats['classes']) }}</div></div>
</div>

<form method="GET" action="{{ route('trainers.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search name, specialization...">
    </div>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="active" @selected(request('status')==='active')>Active</option>
        <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status']))
        <a href="{{ route('trainers.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($trainers->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Trainer</th>
                        <th>Code</th>
                        <th>Specialization</th>
                        <th>Rate</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trainers as $trainer)
                        <tr>
                            <td>
                                <div class="cell-user">
                                    @if($trainer->avatar_url)
                                        <img src="{{ $trainer->avatar_url }}" alt="" class="avatar-img">
                                    @else
                                        <div class="avatar-initials">{{ $trainer->initials }}</div>
                                    @endif
                                    <div>
                                        <span class="name">{{ $trainer->full_name }}</span>
                                        <span class="sub">{{ $trainer->email ?? $trainer->phone ?? '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="code-pill">{{ $trainer->trainer_code }}</span></td>
                            <td>{{ $trainer->specialization ?? '—' }}</td>
                            <td>{{ $trainer->hourly_rate !== null ? money($trainer->hourly_rate).'/hr' : '—' }}</td>
                            <td>{{ $trainer->gym_classes_count }}</td>
                            <td><span class="status-badge {{ $trainer->status }}">{{ $trainer->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('trainers.show', $trainer) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('trainers.edit', $trainer) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form action="{{ route('trainers.destroy', $trainer) }}" method="POST" onsubmit="return confirm('Delete this trainer?')">
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
        @include('partials.pagination', ['paginator' => $trainers])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
            <h3>No trainers found</h3>
            <p>Add coaches to assign them to classes.</p>
            <a href="{{ route('trainers.create') }}" class="btn btn-primary">Add Trainer</a>
        </div>
    @endif
</div>
@endsection
