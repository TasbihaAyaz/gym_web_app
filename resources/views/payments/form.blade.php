@php $isEdit = isset($payment) && $payment; @endphp
<div class="form-card" style="max-width:980px">
    <form method="POST" action="{{ $isEdit ? route('payments.update', $payment) : route('payments.store') }}" id="payment-form">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Member <span class="req">*</span></label>
                <select
                    name="member_id"
                    id="member_id"
                    class="form-select searchable @error('member_id') is-invalid @enderror"
                    data-placeholder="Select member"
                    data-search-placeholder="Search name or member code..."
                    data-context-url="{{ url('/payments/member-context') }}"
                    required
                >
                    <option value="">Select member</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id', $payment->member_id ?? request('member_id')) == $member->id)>
                            {{ $member->full_name }}{{ $member->member_code ? ' ('.$member->member_code.')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('member_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            @unless($isEdit)
            <div class="form-group">
                <label>Package <span class="req">*</span></label>
                <select name="membership_plan_id" id="membership_plan_id" class="form-select searchable @error('membership_plan_id') is-invalid @enderror" data-placeholder="Select package" data-search-placeholder="Search package..." required>
                    <option value="">Select package</option>
                    @foreach(($plans ?? []) as $plan)
                        <option value="{{ $plan->id }}"
                            data-price="{{ $plan->price }}"
                            data-duration="{{ $plan->duration_days }}"
                            data-name="{{ $plan->name }}"
                            @selected((string) old('membership_plan_id') === (string) $plan->id)>
                            {{ $plan->name }} — {{ money($plan->price) }} / {{ $plan->duration_days }} days
                        </option>
                    @endforeach
                </select>
                @error('membership_plan_id')<span class="field-error">{{ $message }}</span>@enderror
                <p class="field-hint">Fee is collected against this package. Underpayment can be waived as discount.</p>
            </div>
            <div class="form-group">
                <label>Fee From Date</label>
                <input type="date" name="fee_start_date" id="fee_start_date" class="form-control" value="{{ old('fee_start_date', now()->format('Y-m-d')) }}">
            </div>
            <div class="form-group">
                <label>Fee Expiry Date</label>
                <input type="date" name="fee_end_date" id="fee_end_date" class="form-control" value="{{ old('fee_end_date', now()->addDays(30)->format('Y-m-d')) }}">
                <p class="field-hint">Set manually anytime. Package days only suggest a date.</p>
            </div>
            @endunless

            <div class="form-group">
                <label>Account</label>
                <select name="account_id" class="form-select">
                    <option value="">Select account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id', $payment->account_id ?? '') == $account->id)>
                            {{ $account->name }} ({{ $account->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Amount paid ({{ currency_symbol() }}) <span class="req">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $payment->amount ?? '') }}" required>
                @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                <p class="field-hint" id="discount-hint" hidden></p>
            </div>
            <div class="form-group">
                <label>Method <span class="req">*</span></label>
                <select name="method" class="form-select" required>
                    @foreach(['cash','card','bank_transfer','online','other'] as $m)
                        <option value="{{ $m }}" @selected(old('method', $payment->method ?? 'cash') === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Payment Date <span class="req">*</span></label>
                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', isset($payment) ? $payment->payment_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Reference</label>
                <input type="text" name="reference" class="form-control" value="{{ old('reference', $payment->reference ?? '') }}" placeholder="Txn / cheque #">
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['completed','pending','failed','refunded'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $payment->status ?? 'completed') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label class="checkbox-label" style="display:flex;gap:8px;align-items:flex-start;cursor:pointer">
                    <input type="checkbox" name="apply_package_discount" value="1" @checked(old('apply_package_discount', true)) style="margin-top:3px">
                    <span>If amount is less than package price, count the remaining as <strong>discount</strong> (clear fee balance)</span>
                </label>
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" class="form-textarea" rows="3">{{ old('notes', $payment->notes ?? '') }}</textarea>
            </div>
        </div>

        @unless($isEdit)
        <div id="member-context" class="member-context" hidden>
            <div class="mc-head">
                <div>
                    <h3 id="mc-name">Member</h3>
                    <p id="mc-meta" class="mc-meta"></p>
                </div>
                <span class="status-badge" id="mc-fee-badge">—</span>
            </div>
            <div class="mc-grid">
                <div class="mc-block">
                    <div class="mc-label">Trainer</div>
                    <div class="mc-value" id="mc-trainer">Self training</div>
                </div>
                <div class="mc-block">
                    <div class="mc-label">Current package</div>
                    <div class="mc-value" id="mc-package">—</div>
                </div>
                <div class="mc-block">
                    <div class="mc-label">Current fee period</div>
                    <div class="mc-value" id="mc-period">—</div>
                </div>
                <div class="mc-block">
                    <div class="mc-label">Last amount paid</div>
                    <div class="mc-value" id="mc-paid">—</div>
                </div>
            </div>
            <div class="mc-split">
                <div>
                    <h4>Previous fee periods</h4>
                    <div class="table-wrap mc-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>From</th>
                                    <th>Expiry</th>
                                    <th>Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="mc-fee-history"></tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h4>Recent payments</h4>
                    <div class="table-wrap mc-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="mc-payments"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endunless

        <div class="form-actions">
            <a href="{{ route('payments.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Payment' : 'Save Fee Payment' }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(() => {
  const symbol = @json(currency_symbol());
  const memberSelect = document.getElementById('member_id');
  const amountInput = document.getElementById('amount');
  const planSelect = document.getElementById('membership_plan_id');
  const startInput = document.getElementById('fee_start_date');
  const endInput = document.getElementById('fee_end_date');
  const hint = document.getElementById('discount-hint');
  const contextBox = document.getElementById('member-context');
  let expiryManual = false;

  function addDays(dateStr, days) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + Number(days || 0));
    return d.toISOString().slice(0, 10);
  }

  function money(n) {
    return symbol + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function suggestEndFromPlan() {
    if (expiryManual || !startInput?.value || !endInput || !planSelect) return;
    const opt = planSelect.selectedOptions?.[0];
    if (!opt?.value) return;
    endInput.value = addDays(startInput.value, Number(opt.dataset.duration || 30));
  }

  function updateDiscountHint() {
    if (!hint) return;
    const planOpt = planSelect?.selectedOptions?.[0];
    const paid = parseFloat(amountInput?.value || '0') || 0;
    const due = planOpt?.value ? (parseFloat(planOpt.dataset.price) || 0) : 0;
    const label = planOpt?.dataset?.name || 'Package';

    if (due > 0 && paid > 0 && paid < due) {
      hint.hidden = false;
      hint.textContent = label + ' ' + money(due) + ' − paid ' + money(paid) + ' → discount ' + money(due - paid) + ' (balance cleared)';
    } else if (due > 0 && paid >= due) {
      hint.hidden = false;
      hint.textContent = 'Full package amount covered. No discount.';
    } else {
      hint.hidden = true;
      hint.textContent = '';
    }
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '—';
  }

  function feeBadgeClass(status) {
    if (status === 'valid') return 'active';
    if (status === 'expiring') return 'leave';
    if (status === 'expired') return 'overdue';
    return 'pending';
  }

  async function loadMemberContext(memberId, applySuggestions) {
    if (!memberId || !contextBox || !memberSelect) {
      if (contextBox) contextBox.hidden = true;
      return;
    }

    const base = memberSelect.dataset.contextUrl || '/payments/member-context';
    try {
      const res = await fetch(base + '/' + memberId, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('Failed');
      const data = await res.json();
      const m = data.member || {};
      const pkg = data.current_package;
      const trainer = data.trainer;

      contextBox.hidden = false;
      setText('mc-name', m.name);
      setText('mc-meta', [m.code, m.bio_id ? 'Bio ' + m.bio_id : null, m.phone, m.status ? ('Status: ' + m.status) : null, m.joined_at ? ('Joined ' + m.joined_at) : null].filter(Boolean).join(' · '));
      setText('mc-trainer', trainer ? (trainer.name + (trainer.phone ? ' · ' + trainer.phone : '')) : 'Self training');
      setText('mc-package', pkg ? (pkg.plan_name + ' · ' + money(pkg.plan_price)) : 'No active package');
      setText('mc-period', pkg ? (pkg.start_label + ' → ' + pkg.end_label) : '—');
      setText('mc-paid', pkg ? money(pkg.amount_paid) : '—');

      const badge = document.getElementById('mc-fee-badge');
      if (badge) {
        const status = pkg?.fee_status || 'none';
        badge.className = 'status-badge ' + feeBadgeClass(status);
        badge.textContent = status === 'none' ? 'no fee' : status;
      }

      const hist = document.getElementById('mc-fee-history');
      if (hist) {
        hist.innerHTML = (data.fee_history || []).length
          ? data.fee_history.map((row) => (
              '<tr><td>' + row.plan + '</td><td>' + row.start + '</td><td>' + row.end + '</td><td>' + money(row.amount_paid) + '</td><td><span class="status-badge ' + row.status + '">' + row.status + '</span></td></tr>'
            )).join('')
          : '<tr><td colspan="5" style="color:var(--text-mute)">No previous fee periods</td></tr>';
      }

      const pays = document.getElementById('mc-payments');
      if (pays) {
        pays.innerHTML = (data.payments || []).length
          ? data.payments.map((row) => (
              '<tr><td>' + row.date + '</td><td>' + money(row.amount) + '</td><td>' + (row.method || '—') + '</td><td><span class="status-badge ' + (row.status === 'completed' ? 'active' : 'pending') + '">' + row.status + '</span></td></tr>'
            )).join('')
          : '<tr><td colspan="4" style="color:var(--text-mute)">No payments recorded</td></tr>';
      }

      if (applySuggestions) {
        const suggested = data.suggested_fee || {};
        if (suggested.plan_id && planSelect) {
          planSelect.value = String(suggested.plan_id);
          planSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (startInput && suggested.start) startInput.value = suggested.start;
        expiryManual = false;
        if (endInput && suggested.end) endInput.value = suggested.end;
        if (amountInput && suggested.amount && !amountInput.dataset.touched) {
          amountInput.value = Number(suggested.amount).toFixed(2);
        }
        updateDiscountHint();
      }
    } catch (err) {
      console.warn('[payment] member context failed', err);
      contextBox.hidden = true;
    }
  }

  planSelect?.addEventListener('change', () => {
    const opt = planSelect.selectedOptions[0];
    if (!opt?.value) {
      updateDiscountHint();
      return;
    }
    if (opt.dataset.price && amountInput && !amountInput.dataset.touched) {
      amountInput.value = Number(opt.dataset.price).toFixed(2);
    }
    if (startInput && !startInput.value) startInput.value = new Date().toISOString().slice(0, 10);
    suggestEndFromPlan();
    updateDiscountHint();
  });

  startInput?.addEventListener('change', suggestEndFromPlan);
  endInput?.addEventListener('input', () => { expiryManual = true; });
  endInput?.addEventListener('change', () => { expiryManual = true; });

  amountInput?.addEventListener('input', () => {
    amountInput.dataset.touched = '1';
    updateDiscountHint();
  });

  memberSelect?.addEventListener('change', () => {
    expiryManual = false;
    if (amountInput) delete amountInput.dataset.touched;
    loadMemberContext(memberSelect.value, true);
  });

  if (memberSelect?.value) {
    loadMemberContext(memberSelect.value, !@json($isEdit));
  }

  updateDiscountHint();
})();
</script>
@endpush

@push('styles')
<style>
.member-context {
  margin-top: 18px;
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: rgba(255,255,255,.02);
}
.mc-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}
.mc-head h3 { font-size: 16px; margin: 0 0 4px; }
.mc-meta { margin: 0; color: var(--text-mute); font-size: 12px; }
.mc-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}
.mc-block {
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(255,255,255,.06);
}
.mc-label { font-size: 11px; color: var(--text-mute); margin-bottom: 4px; }
.mc-value { font-size: 13px; font-weight: 600; }
.mc-split {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 14px;
}
.mc-split h4 { font-size: 12.5px; margin: 0 0 8px; color: var(--text-dim); }
.mc-table { max-height: 220px; overflow: auto; border: 1px solid var(--border); border-radius: 10px; }
.mc-table table { width: 100%; border-collapse: collapse; font-size: 12px; }
.mc-table th, .mc-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); text-align: left; }
.mc-table th { color: var(--text-mute); font-weight: 600; position: sticky; top: 0; background: var(--card, #151521); }
@media (max-width: 900px) {
  .mc-grid, .mc-split { grid-template-columns: 1fr; }
}
</style>
@endpush
