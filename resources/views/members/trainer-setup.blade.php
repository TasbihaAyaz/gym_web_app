@extends('layouts.app')

@section('title', 'Member Trainer Setup')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Member Trainer Setup</h1>
        <p>Search a member, review the current trainer, and assign a new trainer with fees</p>
    </div>
</div>

<div class="form-card pkg-search-card">
    <div class="pkg-card-head">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
        <div>
            <h3>Search Member</h3>
            <p>Bio ID / Name / Member code / Phone</p>
        </div>
    </div>
    <div class="pkg-search-row">
        <input type="text" id="trn-search-input" class="form-control" placeholder="e.g. 743 or Zain…" value="{{ $member?->device_user_id ?: ($member?->full_name ?? '') }}" autocomplete="off">
        <button type="button" id="trn-search-btn" class="btn btn-primary">Search</button>
        <a href="{{ route('members.trainer-setup') }}" class="btn btn-ghost">Clear</a>
    </div>
    <div id="trn-search-results" class="pkg-search-results" hidden></div>
</div>

<form method="POST" action="{{ route('members.trainer-setup.store') }}" id="trn-setup-form">
    @csrf
    <input type="hidden" name="member_id" value="{{ $member?->id }}">

    <div class="trn-grid">
        <div class="form-card pkg-panel">
            <div class="pkg-card-head">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <div>
                    <h3>Current Active Trainer</h3>
                    <p>Read-only member snapshot</p>
                </div>
            </div>
            <div class="pkg-kv">
                <div class="pkg-kv-row">
                    <span>Bio ID</span>
                    <div class="pkg-kv-val">{{ $member?->device_user_id ?: '—' }}</div>
                </div>
                <div class="pkg-kv-row">
                    <span>Name</span>
                    <div class="pkg-kv-val">{{ $member?->full_name ?? '—' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Trainer</span>
                    <div class="pkg-kv-val">{{ $member?->trainer?->full_name ?? 'NONE' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Trainer Fee</span>
                    <div class="pkg-kv-val">{{ $member ? money($member->trainer_fee ?? 0) : '—' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Commission</span>
                    <div class="pkg-kv-val">{{ $member ? money($member->trainer_commission ?? 0) : '—' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Gym Comm.</span>
                    <div class="pkg-kv-val">{{ $member ? money($member->gym_commission ?? 0) : '—' }}</div>
                </div>
            </div>
        </div>

        <div class="form-card pkg-panel">
            <div class="pkg-card-head">
                <svg viewBox="0 0 24 24"><path d="M17 1v6h6"/><path d="M3 11a9 9 0 0 1 15.5-6.4L23 7"/><path d="M7 23v-6H1"/><path d="M21 13a9 9 0 0 1-15.5 6.4L1 17"/></svg>
                <div>
                    <h3>Assign New Trainer</h3>
                    <p>Select trainer and set fees</p>
                </div>
            </div>

            <div class="form-group">
                <label>Select Trainer</label>
                <select name="trainer_id" id="trainer_id" class="form-select searchable" data-placeholder="NONE / Self training" data-search-placeholder="Search trainer…" @disabled(! $member)>
                    <option value="">NONE</option>
                    @foreach($trainers as $trainer)
                        <option
                            value="{{ $trainer->id }}"
                            data-rate="{{ $trainer->hourly_rate ?? 0 }}"
                            @selected((string) old('trainer_id', $member?->trainer_id) === (string) $trainer->id)
                        >
                            {{ $trainer->full_name }}{{ $trainer->specialization ? ' · '.$trainer->specialization : '' }}
                        </option>
                    @endforeach
                </select>
                @error('trainer_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label>Trainer Fee ({{ currency_symbol() }})</label>
                <input type="number" step="0.01" min="0" name="trainer_fee" id="trainer_fee" class="form-control pkg-accent-input" value="{{ old('trainer_fee', $member->trainer_fee ?? '0') }}" @disabled(! $member)>
            </div>
            <div class="form-group">
                <label>Commission ({{ currency_symbol() }})</label>
                <input type="number" step="0.01" min="0" name="trainer_commission" id="trainer_commission" class="form-control" value="{{ old('trainer_commission', $member->trainer_commission ?? '0') }}" @disabled(! $member)>
            </div>
            <div class="form-group">
                <label>Gym Comm. ({{ currency_symbol() }})</label>
                <input type="number" step="0.01" min="0" name="gym_commission" id="gym_commission" class="form-control" value="{{ old('gym_commission', $member->gym_commission ?? '0') }}" @disabled(! $member)>
            </div>
            <div class="form-group">
                <label>Change Type <span class="req">*</span></label>
                <select name="change_type" class="form-select" @disabled(! $member) required>
                    @foreach(['change' => 'Change', 'assign' => 'Assign', 'remove' => 'Remove', 'transfer' => 'Transfer'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('change_type', 'change') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Effective Date <span class="req">*</span></label>
                <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', now()->format('Y-m-d')) }}" @disabled(! $member) required>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-textarea" rows="3" @disabled(! $member) placeholder="Optional">{{ old('remarks') }}</textarea>
            </div>
        </div>
    </div>

    <div class="pkg-actions">
        <a href="{{ route('members.trainer-setup') }}" class="btn btn-ghost">Clear</a>
        <button type="submit" class="btn btn-primary" @disabled(! $member)>Save Trainer Change</button>
    </div>
</form>

<div class="form-card" style="margin-top:18px">
    <div class="pkg-card-head" style="margin-bottom:14px">
        <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
        <div>
            <h3>Trainer History</h3>
            <p>{{ $member ? $member->full_name : 'Select a member to view history' }}</p>
        </div>
    </div>
    @if($member && $member->trainerHistories->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Effective</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Fee</th>
                        <th>Commission</th>
                        <th>Gym Comm.</th>
                        <th>Type</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($member->trainerHistories as $row)
                        <tr>
                            <td>{{ $row->effective_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $row->previous_trainer_name ?: 'NONE' }}</td>
                            <td>{{ $row->trainer?->full_name ?? 'NONE' }}</td>
                            <td>{{ money($row->trainer_fee) }}</td>
                            <td>{{ money($row->trainer_commission) }}</td>
                            <td>{{ money($row->gym_commission) }}</td>
                            <td>{{ ucfirst($row->change_type) }}</td>
                            <td>{{ $row->changer?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p style="color:var(--text-dim);font-size:13px;margin:0">No trainer history yet.</p>
    @endif
</div>
@endsection

@push('styles')
<style>
.pkg-search-card { margin-bottom: 18px; }
.pkg-card-head { display:flex; align-items:flex-start; gap:12px; margin-bottom:14px; }
.pkg-card-head svg { width:22px; height:22px; stroke:var(--purple); fill:none; stroke-width:1.8; flex-shrink:0; margin-top:2px; }
.pkg-card-head h3 { margin:0; font-size:15px; }
.pkg-card-head p { margin:3px 0 0; font-size:12px; color:var(--text-mute); }
.pkg-search-row { display:grid; grid-template-columns:1fr auto auto; gap:10px; align-items:center; }
.pkg-search-results { margin-top:10px; border:1px solid var(--border); border-radius:10px; overflow:hidden; background:var(--card-2); }
.pkg-search-hit { display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-bottom:1px solid var(--border); color:inherit; text-decoration:none; }
.pkg-search-hit:last-child { border-bottom:0; }
.pkg-search-hit:hover { background:var(--hover); }
.pkg-search-hit strong { display:block; font-size:13px; }
.pkg-search-hit span { font-size:12px; color:var(--text-mute); }
.trn-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:16px; }
.pkg-panel .form-group { margin-bottom:12px; }
.pkg-kv { display:grid; gap:8px; }
.pkg-kv-row { display:grid; grid-template-columns:120px 1fr; gap:8px; align-items:center; }
.pkg-kv-row > span { font-size:12px; color:var(--text-mute); font-weight:600; }
.pkg-kv-val { padding:9px 12px; border:1px solid var(--border); border-radius:9px; background:var(--card-2); font-size:13px; font-weight:600; min-height:38px; display:flex; align-items:center; }
.pkg-kv-row.highlight .pkg-kv-val { background:rgba(32,56,224,.08); border-color:rgba(32,56,224,.22); }
.pkg-accent-input { background:rgba(32,56,224,.08)!important; border-color:rgba(32,56,224,.22)!important; }
.pkg-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
@media (max-width: 900px) {
  .trn-grid { grid-template-columns:1fr; }
  .pkg-search-row { grid-template-columns:1fr; }
}
</style>
@endpush

@push('scripts')
<script>
(() => {
  const searchUrl = @json(route('members.trainer-setup.search'));
  const setupUrl = @json(route('members.trainer-setup'));
  const input = document.getElementById('trn-search-input');
  const btn = document.getElementById('trn-search-btn');
  const results = document.getElementById('trn-search-results');
  const trainerSelect = document.getElementById('trainer_id');
  const feeInput = document.getElementById('trainer_fee');

  async function runSearch() {
    const q = (input?.value || '').trim();
    if (!q) {
      results.hidden = true;
      results.innerHTML = '';
      return;
    }
    results.hidden = false;
    results.innerHTML = '<div style="padding:12px;color:var(--text-mute);font-size:13px">Searching…</div>';
    try {
      const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      const list = data.members || [];
      if (!list.length) {
        results.innerHTML = '<div style="padding:12px;color:var(--text-mute);font-size:13px">No members found.</div>';
        return;
      }
      if (list.length === 1) {
        window.location.href = setupUrl + '?member_id=' + list[0].id;
        return;
      }
      results.innerHTML = list.map(m => `
        <a class="pkg-search-hit" href="${setupUrl}?member_id=${m.id}">
          <div>
            <strong>${escapeHtml(m.name)}</strong>
            <span>Bio ${escapeHtml(m.bio_id || '—')} · ${escapeHtml(m.code || '')}</span>
          </div>
          <span>${escapeHtml(m.trainer || 'NONE')}</span>
        </a>
      `).join('');
    } catch (e) {
      results.innerHTML = '<div style="padding:12px;color:var(--red);font-size:13px">Search failed.</div>';
    }
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  trainerSelect?.addEventListener('change', () => {
    const opt = trainerSelect.selectedOptions?.[0];
    if (!opt?.value) {
      if (feeInput) feeInput.value = '0';
      return;
    }
    const rate = Number(opt.dataset.rate || 0);
    if (feeInput && (feeInput.value === '' || Number(feeInput.value) === 0)) {
      feeInput.value = rate.toFixed(2);
    }
  });

  btn?.addEventListener('click', runSearch);
  input?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });
})();
</script>
@endpush
