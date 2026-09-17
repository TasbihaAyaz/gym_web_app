@extends('layouts.app')

@section('title', 'Members')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Members</h1>
        <p>Manage gym members, status, and contact details</p>
    </div>
    <div class="toolbar-actions">
        <form method="GET" action="{{ route('members.export-pending-fees') }}" id="fee-expiry-form" class="toolbar-actions" style="gap:8px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-dim)">
                <span>From</span>
                <input type="date" name="expiry_from" id="expiry_from" class="form-control" style="width:auto;max-width:160px" value="{{ request('expiry_from', now()->startOfMonth()->toDateString()) }}" title="Fee expiry from date" required>
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-dim)">
                <span>To</span>
                <input type="date" name="expiry_to" id="expiry_to" class="form-control" style="width:auto;max-width:160px" value="{{ request('expiry_to', now()->toDateString()) }}" title="Fee expiry to date" required>
            </label>
            <button type="button" class="btn btn-primary" id="fee-expiry-show-btn" data-fee-expiry-url="{{ route('members.pending-fees') }}">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Show Fee Expiry
            </button>
            <button type="submit" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                Export
            </button>
        </form>
        <a href="{{ route('members.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Member
        </a>
    </div>
</div>

{{-- Fee expiry popup --}}
<div class="fee-expiry-modal-root" id="fee-expiry-modal" aria-hidden="true">
    <div class="fee-expiry-modal-backdrop" data-fee-expiry-close></div>
    <div class="fee-expiry-modal" role="dialog" aria-modal="true" aria-labelledby="fee-expiry-modal-title">
        <div class="fee-expiry-modal-head">
            <div>
                <h2 id="fee-expiry-modal-title">Fee Expiry</h2>
                <p id="fee-expiry-modal-range">Select a date range</p>
            </div>
            <button type="button" class="btn-icon" data-fee-expiry-close title="Close" aria-label="Close">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="fee-expiry-modal-body">
            <div id="fee-expiry-loading" class="fee-expiry-loading" hidden>
                <span class="spinner"></span>
                <span>Loading…</span>
            </div>
            <div id="fee-expiry-error" class="fee-expiry-error" hidden></div>
            <div class="table-wrap" id="fee-expiry-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Bio ID</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Fee Period</th>
                            <th>Days Overdue</th>
                            <th>Fee Status</th>
                        </tr>
                    </thead>
                    <tbody id="fee-expiry-rows"></tbody>
                </table>
            </div>
            <p id="fee-expiry-empty" class="fee-expiry-empty" hidden>No members with fee expiry in this date range.</p>
        </div>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Members</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Active</div><div class="value" style="color:#22c55e">{{ number_format($stats['active']) }}</div></div>
    <div class="mini-stat"><div class="label">Pending</div><div class="value" style="color:#3b82f6">{{ number_format($stats['pending']) }}</div></div>
    <div class="mini-stat"><div class="label">Cancelled</div><div class="value" style="color:#ef4444">{{ number_format($stats['cancelled']) }}</div></div>
</div>

<form method="GET" action="{{ route('members.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search name, email, code...">
    </div>
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach(['active','pending','leave','cancelled'] as $st)
            <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
        @endforeach
    </select>
    <select name="gender" class="form-select" onchange="this.form.submit()">
        <option value="">All Genders</option>
        @foreach(['male','female','other'] as $g)
            <option value="{{ $g }}" @selected(request('gender') === $g)>{{ ucfirst($g) }}</option>
        @endforeach
    </select>
    <select name="fee_status" class="form-select" onchange="this.form.submit()">
        <option value="">All Fee Status</option>
        <option value="active" @selected(request('fee_status') === 'active')>Fee Active</option>
        <option value="expiring" @selected(request('fee_status') === 'expiring')>Expiring (7 days)</option>
        <option value="expired" @selected(request('fee_status') === 'expired')>Fee Expired</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status','gender','fee_status']))
        <a href="{{ route('members.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($members->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Trainer</th>
                        <th>Bio ID</th>
                        <th>Phone</th>
                        <th>Fee Period</th>
                        <th>Fee Expiry</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody id="members-rows">
                    @include('members.partials.rows', ['members' => $members])
                </tbody>
            </table>
        </div>

        @php
            $scrollParams = [
                'search' => request('search'),
                'status' => request('status'),
                'gender' => request('gender'),
                'fee_status' => request('fee_status'),
            ];
        @endphp
        <div
            class="infinite-scroll"
            data-infinite-scroll
            data-url="{{ route('members.index') }}"
            data-target="#members-rows"
            data-next-page="{{ $members->hasMorePages() ? $members->currentPage() + 1 : '' }}"
            data-params="{{ json_encode($scrollParams) }}"
        >
            <div class="infinite-spinner" hidden>
                <span class="spinner"></span>
                <span>Loading more…</span>
            </div>
            <div class="infinite-end" hidden>All {{ number_format($members->total()) }} members loaded</div>
        </div>
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            <h3>No members found</h3>
            <p>Add your first member or adjust the filters.</p>
            <a href="{{ route('members.create') }}" class="btn btn-primary">Add Member</a>
        </div>
    @endif
</div>

@if(session('biometric_popup'))
@php $bio = session('biometric_popup'); @endphp
<div class="bio-modal-root" id="bio-modal-root">
    <div class="bio-modal-backdrop" onclick="document.getElementById('bio-modal-root').remove()"></div>
    <div class="bio-modal" role="dialog" aria-modal="true">
        <div class="bio-modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 11c0-1.5-1.2-2.5-2.5-2.5S7 9.5 7 11"/><path d="M12 11v3.5"/><path d="M12 7.5c0-2.5-2-4.5-4.5-4.5S3 5 3 7.5"/><path d="M17 11c0-2.8-2.2-5-5-5"/><path d="M7 14.5c0 2.5 2 4.5 4.5 4.5"/></svg>
        </div>
        <h2>{{ !empty($bio['device_ok']) ? 'Saved on biometric machine' : 'Biometric ID generated' }}</h2>
        <p class="bio-modal-name">{{ $bio['name'] }}</p>
        <div class="bio-modal-id">{{ $bio['id'] }}</div>
        @if(!empty($bio['device_ok']))
            <p class="bio-modal-help">
                On the K50 open <strong>All Members</strong>, search ID <strong>{{ $bio['id'] }}</strong>,
                then <strong>Edit</strong> and enroll the thumb.
            </p>
        @else
            <p class="bio-modal-help" style="color:#f87171">
                {{ $bio['device_message'] ?? 'Could not save on machine. Check device connection, then edit this member to retry.' }}
            </p>
        @endif
        <p class="bio-modal-code">Member code: {{ $bio['code'] }}</p>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('bio-modal-root').remove()">Got it</button>
    </div>
</div>
<style>
.bio-modal-root{position:fixed;inset:0;z-index:1300;display:grid;place-items:center;padding:20px}
.bio-modal-backdrop{position:absolute;inset:0;background:rgba(5,5,12,.65);backdrop-filter:blur(8px)}
.bio-modal{position:relative;width:min(420px,100%);background:linear-gradient(180deg,#1a1a28,#12121c);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:28px 24px;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.55)}
.bio-modal-icon{width:64px;height:64px;margin:0 auto 14px;border-radius:18px;display:grid;place-items:center;background:rgba(32, 56, 224,.18);color:#2038e0}
.bio-modal-icon svg{width:32px;height:32px}
.bio-modal h2{font-size:18px;margin-bottom:6px}
.bio-modal-name{color:var(--text-dim);font-size:13.5px;margin-bottom:14px}
.bio-modal-id{font-size:48px;font-weight:800;letter-spacing:-.04em;color:#2038e0;line-height:1;margin-bottom:14px}
.bio-modal-help{font-size:13px;color:var(--text-dim);line-height:1.55;margin-bottom:10px}
.bio-modal-code{font-size:12px;color:var(--text-mute);margin-bottom:18px}
</style>
@endif
@endsection

@push('styles')
<style>
.fee-expiry-modal-root {
  position: fixed;
  inset: 0;
  z-index: 1250;
  display: none;
  place-items: center;
  padding: 20px;
}
.fee-expiry-modal-root.is-open { display: grid; }
.fee-expiry-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, .4);
  backdrop-filter: blur(6px);
}
.fee-expiry-modal {
  position: relative;
  width: min(960px, 100%);
  max-height: min(90vh, 860px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  box-shadow: 0 24px 64px rgba(15, 23, 42, .18);
  color: #0f172a;
}
.fee-expiry-modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 20px 22px 14px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  flex-shrink: 0;
}
.fee-expiry-modal-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
}
.fee-expiry-modal-head p {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: #64748b;
}
.fee-expiry-modal-head .btn-icon {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #334155;
}
.fee-expiry-modal-head .btn-icon:hover {
  background: #f1f5f9;
  color: #0f172a;
}
.fee-expiry-modal-body {
  padding: 14px 18px 20px;
  overflow: auto;
  flex: 1;
  min-height: 0;
  background: #ffffff;
}
.fee-expiry-modal-body .data-table {
  color: #0f172a;
}
.fee-expiry-modal-body .data-table th {
  color: #64748b;
  border-bottom-color: #e2e8f0;
}
.fee-expiry-modal-body .data-table td {
  border-bottom-color: #f1f5f9;
  color: #1e293b;
}
.fee-expiry-modal-body .data-table tbody tr:hover td {
  background: #f8fafc;
}
.fee-expiry-modal-body .data-table a {
  color: #0f172a;
}
.fee-expiry-modal-body .code-pill {
  background: #f1f5f9;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}
.fee-expiry-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 36px;
  color: #64748b;
  font-size: 13.5px;
}
.fee-expiry-loading[hidden],
.fee-expiry-error[hidden],
.fee-expiry-empty[hidden],
#fee-expiry-table-wrap[hidden] {
  display: none !important;
}
.fee-expiry-error {
  padding: 14px 16px;
  border-radius: 10px;
  background: #fef2f2;
  color: #dc2626;
  font-size: 13px;
  margin-bottom: 10px;
  border: 1px solid #fecaca;
}
.fee-expiry-empty {
  text-align: center;
  padding: 36px 16px;
  color: #64748b;
  font-size: 13.5px;
  margin: 0;
}
.fee-expiry-modal-body .data-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f8fafc;
}
.fee-expiry-status {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
}
.fee-expiry-status.expired { background: #fef2f2; color: #dc2626; }
.fee-expiry-status.today { background: #fffbeb; color: #d97706; }
.fee-expiry-status.upcoming { background: #f0fdf4; color: #16a34a; }
body.fee-expiry-modal-open { overflow: hidden; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/infinite-scroll.js') }}?v=1"></script>
<script>
(function () {
  const modal = document.getElementById('fee-expiry-modal');
  const btn = document.getElementById('fee-expiry-show-btn');
  const fromInput = document.getElementById('expiry_from');
  const toInput = document.getElementById('expiry_to');
  if (!modal || !btn || !fromInput || !toInput) return;

  const rangeEl = document.getElementById('fee-expiry-modal-range');
  const loadingEl = document.getElementById('fee-expiry-loading');
  const errorEl = document.getElementById('fee-expiry-error');
  const emptyEl = document.getElementById('fee-expiry-empty');
  const tableWrap = document.getElementById('fee-expiry-table-wrap');
  const rowsEl = document.getElementById('fee-expiry-rows');
  const url = btn.dataset.feeExpiryUrl;

  function openModal() {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('fee-expiry-modal-open');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('fee-expiry-modal-open');
  }

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function statusClass(label) {
    if (label === 'Expired') return 'expired';
    if (label === 'Expires today') return 'today';
    return 'upcoming';
  }

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  async function loadFeeExpiry() {
    const from = fromInput.value;
    const to = toInput.value;
    if (!from || !to) {
      alert('Please select both From and To dates.');
      return;
    }
    if (from > to) {
      alert('From date must be on or before To date.');
      return;
    }

    openModal();
    loadingEl.hidden = false;
    errorEl.hidden = true;
    emptyEl.hidden = true;
    tableWrap.hidden = true;
    rowsEl.innerHTML = '';
    rangeEl.textContent = formatDate(from) + ' → ' + formatDate(to);

    try {
      const qs = new URLSearchParams({ expiry_from: from, expiry_to: to });
      const res = await fetch(url + '?' + qs.toString(), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        const msg = data.message || (data.errors && Object.values(data.errors).flat().join(' ')) || 'Could not load fee expiry data.';
        throw new Error(msg);
      }

      rangeEl.textContent = formatDate(data.from) + ' → ' + formatDate(data.to) + ' · ' + data.count + ' member' + (data.count === 1 ? '' : 's');

      if (!data.rows || !data.rows.length) {
        emptyEl.hidden = false;
        return;
      }

      rowsEl.innerHTML = data.rows.map((row) => {
        const period = (row.fee_start || row.fee_end)
          ? esc(formatDate(row.fee_start)) + ' → ' + esc(formatDate(row.fee_end))
          : '—';
        const overdue = row.days_overdue > 0
          ? '<span style="color:#ef4444;font-weight:700">' + row.days_overdue + '</span>'
          : '—';
        return '<tr>' +
          '<td><a href="' + esc(row.url) + '" style="font-weight:600;color:inherit">' + esc(row.name) + '</a></td>' +
          '<td><span class="code-pill">' + esc(row.bio_id || '—') + '</span></td>' +
          '<td>' + esc(row.phone || '—') + '</td>' +
          '<td>' + esc(row.plan || '—') + '</td>' +
          '<td style="font-size:12.5px">' + period + '</td>' +
          '<td>' + overdue + '</td>' +
          '<td><span class="fee-expiry-status ' + statusClass(row.fee_status) + '">' + esc(row.fee_status) + '</span></td>' +
          '</tr>';
      }).join('');
      tableWrap.hidden = false;
    } catch (err) {
      errorEl.textContent = err.message || 'Could not load fee expiry data.';
      errorEl.hidden = false;
    } finally {
      loadingEl.hidden = true;
    }
  }

  btn.addEventListener('click', loadFeeExpiry);
  modal.querySelectorAll('[data-fee-expiry-close]').forEach((el) => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
</script>
@endpush
