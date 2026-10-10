@extends('layouts.app')

@section('title', 'Member Package Setup')

@section('content')
@php
    $sub = $member?->activeSubscription;
    $planPrice = (float) ($sub?->plan?->price ?? 0);
@endphp

<div class="page-toolbar">
    <div>
        <h1>Member Package Setup</h1>
        <p>Search a member, review the active package, assign a new one, and optionally record payment</p>
    </div>
</div>

{{-- Search --}}
<div class="form-card pkg-search-card">
    <div class="pkg-card-head">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
        <div>
            <h3>Search Member</h3>
            <p>Bio ID / Name / Member code / Phone</p>
        </div>
    </div>
    <div class="pkg-search-row">
        <input type="text" id="pkg-search-input" class="form-control" placeholder="e.g. 78 or Irfan…" value="{{ $member?->device_user_id ?: ($member?->full_name ?? '') }}" autocomplete="off">
        <button type="button" id="pkg-search-btn" class="btn btn-primary">Search</button>
        <a href="{{ route('members.package-setup') }}" class="btn btn-ghost">Clear</a>
    </div>
    <div id="pkg-search-results" class="pkg-search-results" hidden></div>
</div>

<form method="POST" action="{{ route('members.package-setup.store') }}" id="pkg-setup-form">
    @csrf
    <input type="hidden" name="member_id" id="member_id" value="{{ $member?->id }}">

    <div class="pkg-grid">
        {{-- Current Active Package --}}
        <div class="form-card pkg-panel">
            <div class="pkg-card-head">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                <div>
                    <h3>Current Active Package</h3>
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
                <div class="pkg-kv-row">
                    <span>Contact</span>
                    <div class="pkg-kv-val">{{ $member?->phone ?? '—' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Active Package</span>
                    <div class="pkg-kv-val">{{ $sub?->plan?->name ?? 'No active package' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Monthly Fee</span>
                    <div class="pkg-kv-val">{{ $sub ? money($sub->amount_paid) : '—' }}</div>
                </div>
                <div class="pkg-kv-row highlight">
                    <span>Pkg Expiry</span>
                    <div class="pkg-kv-val">{{ $sub?->end_date?->format('d M Y') ?? '—' }}</div>
                </div>
                @if($sub && (float) ($sub->balance_due ?? 0) > 0)
                <div class="pkg-kv-row highlight">
                    <span>Balance Due</span>
                    <div class="pkg-kv-val" style="color:var(--red)">{{ money($sub->balance_due) }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Assign New Package --}}
        <div class="form-card pkg-panel">
            <div class="pkg-card-head">
                <svg viewBox="0 0 24 24"><path d="M17 1v6h6"/><path d="M3 11a9 9 0 0 1 15.5-6.4L23 7"/><path d="M7 23v-6H1"/><path d="M21 13a9 9 0 0 1-15.5 6.4L1 17"/></svg>
                <div>
                    <h3>Assign New Package</h3>
                    <p>Choose package and effective date</p>
                </div>
            </div>
            <div class="form-group">
                <label>Select Package <span class="req">*</span></label>
                <select name="membership_plan_id" id="membership_plan_id" class="form-select searchable" data-placeholder="Select package" data-search-placeholder="Search package…" @disabled(! $member) required>
                    <option value="">Select package</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}"
                            data-price="{{ $plan->price }}"
                            data-duration="{{ $plan->duration_days }}"
                            @selected((string) old('membership_plan_id', $sub?->membership_plan_id) === (string) $plan->id)>
                            {{ $plan->name }} — {{ money($plan->price) }}
                        </option>
                    @endforeach
                </select>
                @error('membership_plan_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Monthly Fee ({{ currency_symbol() }}) <span class="req">*</span></label>
                <input type="number" step="0.01" min="0" name="monthly_fee" id="monthly_fee" class="form-control pkg-accent-input" value="{{ old('monthly_fee', $sub?->amount_paid ?? '') }}" @disabled(! $member) required>
                @error('monthly_fee')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Pkg Fee ({{ currency_symbol() }})</label>
                <input type="text" id="pkg_fee_display" class="form-control" value="{{ $planPrice > 0 ? number_format($planPrice, 2, '.', '') : '' }}" readonly>
            </div>
            <div class="form-group">
                <label>Effective Date <span class="req">*</span></label>
                <input type="date" name="effective_date" id="effective_date" class="form-control" value="{{ old('effective_date', now()->format('Y-m-d')) }}" @disabled(! $member) required>
                @error('effective_date')<span class="field-error">{{ $message }}</span>@enderror
                <p class="field-hint" id="pkg-end-hint">Expiry auto-fills from package duration.</p>
            </div>
            <div class="form-group">
                <label>Change Type <span class="req">*</span></label>
                <select name="change_type" class="form-select" @disabled(! $member) required>
                    @foreach(['renew' => 'Renew', 'new' => 'New', 'upgrade' => 'Upgrade', 'downgrade' => 'Downgrade', 'transfer' => 'Transfer'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('change_type', 'renew') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Balance Due ({{ currency_symbol() }})</label>
                <input type="number" step="0.01" min="0" name="balance" id="balance" class="form-control" value="{{ old('balance', $sub->balance_due ?? '0') }}" @disabled(! $member)>
            </div>
        </div>

        {{-- Payment Optional --}}
        <div class="form-card pkg-panel">
            <div class="pkg-card-head">
                <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M3 10h18"/><path d="m5 6 7-3 7 3"/><path d="M4 10v11M20 10v11"/></svg>
                <div>
                    <h3>Payment (Optional)</h3>
                    <p>Record cash only if collecting now</p>
                </div>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-textarea" rows="3" @disabled(! $member) placeholder="Optional notes">{{ old('remarks') }}</textarea>
            </div>
            <label class="switch-row compact" style="margin-bottom:14px">
                <input type="checkbox" name="record_payment" id="record_payment" value="1" @checked(old('record_payment')) @disabled(! $member)>
                <span><strong>Record payment in account</strong></span>
            </label>
            <div id="payment-fields" class="pkg-payment-fields">
                <div class="form-group">
                    <label>Amount ({{ currency_symbol() }})</label>
                    <input type="number" step="0.01" min="0" name="payment_amount" id="payment_amount" class="form-control" value="{{ old('payment_amount') }}" @disabled(! $member)>
                </div>
                <div class="form-group">
                    <label>Account</label>
                    <select name="account_id" class="form-select" @disabled(! $member)>
                        <option value="">Default cash</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id', $defaultAccountId) === (string) $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Cheque / Ref No</label>
                    <input type="text" name="cheque_no" class="form-control" value="{{ old('cheque_no') }}" @disabled(! $member) placeholder="Optional">
                </div>
            </div>
        </div>
    </div>

    <div class="pkg-actions">
        <a href="{{ route('members.package-setup') }}" class="btn btn-ghost">Clear</a>
        <button type="submit" class="btn btn-primary" @disabled(! $member)>Save Package</button>
    </div>
</form>

{{-- Package History --}}
<div class="form-card" style="margin-top:18px">
    <div class="pkg-card-head" style="margin-bottom:14px">
        <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
        <div>
            <h3>Package History</h3>
            <p>{{ $member ? $member->full_name : 'Select a member to view history' }}</p>
        </div>
    </div>
    @if($member && $member->subscriptions->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>From</th>
                        <th>Expiry</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($member->subscriptions as $row)
                        <tr>
                            <td>{{ $row->plan?->name ?? '—' }}</td>
                            <td>{{ $row->start_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $row->end_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ money($row->amount_paid) }}</td>
                            <td>{{ money($row->balance_due ?? 0) }}</td>
                            <td><span class="status-badge {{ $row->status }}">{{ $row->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($member->payments->isNotEmpty())
            <h4 style="margin:18px 0 10px;font-size:13px;color:var(--text-dim)">Recent fee payments</h4>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payment #</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($member->payments as $pay)
                            <tr>
                                <td>{{ $pay->payment_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $pay->payment_number }}</td>
                                <td>{{ $pay->plan?->name ?? '—' }}</td>
                                <td>{{ money($pay->amount) }}</td>
                                <td><span class="status-badge {{ $pay->status }}">{{ $pay->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <p style="color:var(--text-dim);font-size:13px;margin:0">No package history yet.</p>
    @endif
</div>
@endsection

@push('styles')
<style>
.pkg-search-card { margin-bottom: 18px; }
.pkg-card-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}
.pkg-card-head svg {
  width: 22px;
  height: 22px;
  stroke: var(--purple);
  fill: none;
  stroke-width: 1.8;
  flex-shrink: 0;
  margin-top: 2px;
}
.pkg-card-head h3 { margin: 0; font-size: 15px; }
.pkg-card-head p { margin: 3px 0 0; font-size: 12px; color: var(--text-mute); }
.pkg-search-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: center;
}
.pkg-search-results {
  margin-top: 10px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--card-2);
}
.pkg-search-hit {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  color: inherit;
  text-decoration: none;
}
.pkg-search-hit:last-child { border-bottom: 0; }
.pkg-search-hit:hover { background: var(--hover); }
.pkg-search-hit strong { display: block; font-size: 13px; }
.pkg-search-hit span { font-size: 12px; color: var(--text-mute); }
.pkg-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-top: 4px;
}
.pkg-panel .form-group { margin-bottom: 12px; }
.pkg-kv { display: grid; gap: 8px; }
.pkg-kv-row {
  display: grid;
  grid-template-columns: 110px 1fr;
  gap: 8px;
  align-items: center;
}
.pkg-kv-row > span {
  font-size: 12px;
  color: var(--text-mute);
  font-weight: 600;
}
.pkg-kv-val {
  padding: 9px 12px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--card-2);
  font-size: 13px;
  font-weight: 600;
  min-height: 38px;
  display: flex;
  align-items: center;
}
.pkg-kv-row.highlight .pkg-kv-val {
  background: rgba(32, 56, 224, 0.08);
  border-color: rgba(32, 56, 224, 0.22);
}
.pkg-accent-input {
  background: rgba(32, 56, 224, 0.08) !important;
  border-color: rgba(32, 56, 224, 0.22) !important;
}
.pkg-payment-fields {
  padding: 12px;
  border-radius: 10px;
  background: var(--card-2);
  border: 1px solid var(--border);
}
.pkg-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}
@media (max-width: 1100px) {
  .pkg-grid { grid-template-columns: 1fr; }
  .pkg-search-row { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
(() => {
  const searchUrl = @json(route('members.package-setup.search'));
  const setupUrl = @json(route('members.package-setup'));
  const input = document.getElementById('pkg-search-input');
  const btn = document.getElementById('pkg-search-btn');
  const results = document.getElementById('pkg-search-results');
  const planSelect = document.getElementById('membership_plan_id');
  const monthlyFee = document.getElementById('monthly_fee');
  const pkgFee = document.getElementById('pkg_fee_display');
  const effectiveDate = document.getElementById('effective_date');
  const endHint = document.getElementById('pkg-end-hint');
  const recordPayment = document.getElementById('record_payment');
  const paymentAmount = document.getElementById('payment_amount');
  const balanceInput = document.getElementById('balance');

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
          <span>${escapeHtml(m.package || 'No package')}</span>
        </a>
      `).join('');
    } catch (e) {
      results.innerHTML = '<div style="padding:12px;color:var(--red);font-size:13px">Search failed.</div>';
    }
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function syncFromPlan() {
    const opt = planSelect?.selectedOptions?.[0];
    if (!opt?.value) return;
    const price = Number(opt.dataset.price || 0);
    const duration = Number(opt.dataset.duration || 30);
    if (pkgFee) pkgFee.value = price.toFixed(2);
    if (monthlyFee && (monthlyFee.value === '' || monthlyFee.dataset.autofill !== '0')) {
      monthlyFee.value = price.toFixed(2);
      monthlyFee.dataset.autofill = '1';
    }
    if (paymentAmount && recordPayment?.checked && (!paymentAmount.value || paymentAmount.dataset.autofill === '1')) {
      paymentAmount.value = price.toFixed(2);
      paymentAmount.dataset.autofill = '1';
    }
    if (balanceInput && balanceInput.dataset.autofill !== '0') {
      const paid = Number(monthlyFee?.value || 0);
      balanceInput.value = Math.max(0, price - paid).toFixed(2);
    }
    if (endHint && effectiveDate?.value) {
      const d = new Date(effectiveDate.value + 'T00:00:00');
      d.setDate(d.getDate() + duration);
      const label = d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
      endHint.textContent = 'Package expires on ' + label + ' (' + duration + ' days).';
    }
  }

  btn?.addEventListener('click', runSearch);
  input?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });
  planSelect?.addEventListener('change', () => {
    if (monthlyFee) monthlyFee.dataset.autofill = '1';
    if (balanceInput) balanceInput.dataset.autofill = '1';
    syncFromPlan();
  });
  monthlyFee?.addEventListener('input', () => {
    monthlyFee.dataset.autofill = '0';
    const opt = planSelect?.selectedOptions?.[0];
    const price = Number(opt?.dataset.price || 0);
    if (balanceInput && balanceInput.dataset.autofill !== '0') {
      balanceInput.value = Math.max(0, price - Number(monthlyFee.value || 0)).toFixed(2);
    }
  });
  balanceInput?.addEventListener('input', () => { balanceInput.dataset.autofill = '0'; });
  paymentAmount?.addEventListener('input', () => { paymentAmount.dataset.autofill = '0'; });
  effectiveDate?.addEventListener('change', syncFromPlan);
  recordPayment?.addEventListener('change', () => {
    if (recordPayment.checked) {
      const opt = planSelect?.selectedOptions?.[0];
      const price = Number(opt?.dataset.price || monthlyFee?.value || 0);
      if (paymentAmount && !paymentAmount.value) {
        paymentAmount.value = price.toFixed(2);
        paymentAmount.dataset.autofill = '1';
      }
    }
  });
  syncFromPlan();
})();
</script>
@endpush
