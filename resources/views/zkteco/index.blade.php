@extends('layouts.app')

@section('title', 'Biometric Device')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>ZKTeco K50</h1>
        <p>Fingerprint check-in machine — ADMS push into Fit Generation attendance</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('zkteco.welcome') }}" class="btn btn-primary" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Open Check-in Display
        </a>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Devices</div><div class="value">{{ number_format($stats['devices']) }}</div></div>
    <div class="mini-stat"><div class="label">Online (5 min)</div><div class="value" style="color:#22c55e">{{ number_format($stats['online']) }}</div></div>
    <div class="mini-stat"><div class="label">Punches Today</div><div class="value">{{ number_format($stats['today']) }}</div></div>
    <div class="mini-stat"><div class="label">Unmatched PIN</div><div class="value" style="color:#f59e0b">{{ number_format($stats['unmatched']) }}</div></div>
</div>

<div class="form-card" style="margin-bottom:18px">
    <div class="card-head" style="margin-bottom:14px">
        <h3 style="font-size:14.5px">Connect K50 by IP (LAN pull)</h3>
    </div>
    <form method="POST" action="{{ route('zkteco.connect') }}" class="filter-bar" style="margin-bottom:0;align-items:flex-end">
        @csrf
        <div class="form-group" style="margin:0;min-width:180px">
            <label>Device IP</label>
            <input type="text" name="ip" class="form-control" value="{{ $defaultDeviceIp }}" placeholder="192.168.100.205" required>
        </div>
        <div class="form-group" style="margin:0;min-width:110px">
            <label>Port</label>
            <input type="number" name="port" class="form-control" value="{{ old('port', 4370) }}">
        </div>
        <div class="form-group" style="margin:0;min-width:130px">
            <label>Sync last days</label>
            <input type="number" name="sync_days" class="form-control" value="{{ old('sync_days', 2) }}" min="1" max="30">
        </div>
        <button type="submit" class="btn btn-primary">Connect &amp; Sync</button>
    </form>
    <p style="font-size:12px;color:var(--text-dim);margin-top:10px">
        This PC is <code>{{ $pcLanIp }}</code>. Device should be on the same network.
        For live push also set K50 Cloud Server to <code>{{ $pcLanIp }}</code> port <code>80</code>.
    </p>
</div>

<div class="report-grid" style="margin-bottom:18px">
    <div class="form-card">
        <div class="card-head" style="margin-bottom:12px">
            <h3 style="font-size:14.5px">Optional: ADMS realtime push</h3>
        </div>
        <ol style="padding-left:18px;font-size:13px;line-height:1.7;color:var(--text)">
            <li>On K50: <strong>Menu → COMM → Cloud Server Setting</strong>.</li>
            <li>Enable Cloud Server / ADMS.</li>
            <li><strong>Server Address:</strong> <code>{{ $pcLanIp }}</code> (this PC).</li>
            <li><strong>Server Port:</strong> <code>80</code>.</li>
            <li>Enroll members with the same <strong>Device PIN</strong> as in Members.</li>
        </ol>
        <div style="margin-top:14px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-elev)">
            <div style="font-size:11.5px;color:var(--text-mute);margin-bottom:4px">ADMS endpoint</div>
            <div style="font-weight:600;word-break:break-all">http://{{ $pcLanIp }}/fit-generation/iclock/cdata</div>
        </div>
    </div>

    <div class="form-card">
        <div class="card-head" style="margin-bottom:12px">
            <h3 style="font-size:14.5px">How punches map</h3>
        </div>
        <ul style="padding-left:18px;font-size:13px;line-height:1.7;color:var(--text)">
            <li>Device user ID / PIN → member <strong>Device PIN</strong> field.</li>
            <li>First scan of the day → <strong>check-in</strong>.</li>
            <li>Later scan same day → <strong>check-out</strong>.</li>
            <li>Unmatched PIN stays in punch log until you set Device PIN on the member.</li>
            <li>Attendance method is set to <strong>biometric</strong>.</li>
        </ul>
        <p style="font-size:12.5px;color:var(--text-dim);margin-top:12px">
            Tip: set Device PIN to a simple number (e.g. <code>12</code>) and enroll that same ID on the K50.
        </p>
        <a href="{{ route('members.index') }}" class="btn btn-secondary" style="margin-top:12px">Open Members</a>
        <a href="{{ route('attendance.index') }}" class="btn btn-ghost" style="margin-top:12px">View Attendance</a>
        <a href="{{ route('zkteco.welcome') }}" class="btn btn-primary" style="margin-top:12px" target="_blank" rel="noopener">Check-in Display</a>
    </div>
</div>

<div class="table-card" style="margin-bottom:18px">
    <div class="card-head" style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <h3 style="font-size:14.5px">Registered devices</h3>
    </div>
    @if($devices->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Serial</th>
                        <th>Model</th>
                        <th>IP</th>
                        <th>Last seen</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        <tr>
                            <td style="font-weight:600">{{ $device->name ?: 'ZKTeco' }}</td>
                            <td><span class="code-pill">{{ $device->serial_number }}</span></td>
                            <td>{{ $device->model ?: '—' }}</td>
                            <td>{{ $device->ip_address ?: '—' }}</td>
                            <td>{{ $device->last_seen_at?->format('M d, Y H:i') ?? 'Never' }}</td>
                            <td>
                                @if($device->isOnline())
                                    <span class="status-badge active">Online</span>
                                @else
                                    <span class="status-badge leave">Offline</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    @if($device->ip_address)
                                        <form action="{{ route('zkteco.sync', $device) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn-icon" title="Sync now">
                                                <svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-15.5-6.4"/><path d="M3 4v5h5"/><path d="M3 12a9 9 0 0 0 15.5 6.4"/><path d="M21 20v-5h-5"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('zkteco.destroy', $device) }}" method="POST" onsubmit="return confirm('Remove this device record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon danger" title="Remove">
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
    @else
        <div class="empty-state">
            <h3>No device connected yet</h3>
            <p>Configure ADMS on the K50. It will appear here after the first handshake.</p>
        </div>
    @endif
</div>

<div class="table-card">
    <div class="card-head" style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <h3 style="font-size:14.5px">Recent punches</h3>
    </div>
    @if($punches->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Device PIN</th>
                        <th>Member</th>
                        <th>Serial</th>
                        <th>Applied as</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($punches as $punch)
                        <tr>
                            <td>{{ $punch->punched_at?->format('M d, Y H:i:s') }}</td>
                            <td><span class="code-pill">{{ $punch->device_user_id }}</span></td>
                            <td>
                                @if($punch->member)
                                    <a href="{{ route('members.show', $punch->member) }}">{{ $punch->member->full_name }}</a>
                                @else
                                    <span style="color:var(--text-mute)">Not linked</span>
                                @endif
                            </td>
                            <td style="font-size:12px;color:var(--text-mute)">{{ $punch->serial_number }}</td>
                            <td><span class="status-badge {{ $punch->applied_as === 'unmatched' ? 'leave' : ($punch->applied_as === 'ignored' ? 'pending' : 'active') }}">{{ str_replace('_', ' ', $punch->applied_as) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $punches])
    @else
        <div class="empty-state">
            <h3>No punches yet</h3>
            <p>When a member scans on the K50, punches show here and update Attendance.</p>
        </div>
    @endif
</div>
@endsection
