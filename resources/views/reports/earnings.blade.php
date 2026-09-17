@extends('layouts.app')

@section('title', $title)

@section('content')
@include('reports._toolbar')

<form method="GET" action="{{ route('reports.index') }}" class="filter-bar no-print" style="margin-bottom:16px">
    <input type="hidden" name="report" value="earnings">
    <input type="date" name="from" class="form-control" value="{{ $from }}" title="From date" required>
    <input type="date" name="to" class="form-control" value="{{ $to }}" title="To date" required>
    <button type="submit" class="btn btn-primary">Apply</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()">Print / PDF</button>
    <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
</form>

<div class="sales-sheet" id="sales-sheet">
    <div class="form-card ss-header">
        <div class="ss-top">
            <div class="ss-brand">
                <div class="ss-logo"><img src="{{ asset('assets/img/logo.jpg') }}" alt="Fit Generation"></div>
                <div>
                    <div class="ss-name">{{ strtoupper($gym['name'] ?: 'FIT GENERATION') }}</div>
                    @if($gym['address'])
                        <div class="ss-meta">{{ $gym['address'] }}</div>
                    @endif
                    @if($gym['phone'])
                        <div class="ss-meta">Phone: {{ $gym['phone'] }}</div>
                    @endif
                </div>
            </div>
            <div class="ss-badge">
                <strong>{{ $periodLabel }}</strong>
                <span>Voucher detail</span>
            </div>
        </div>

        <div class="ss-title-row">
            <div>
                <h1>{{ $title }}</h1>
                <p class="ss-subtitle">Fee payments for the selected date range</p>
            </div>
            <div class="ss-meta-grid">
                <div><span>Period</span><strong>{{ $periodDisplay }}</strong></div>
                <div><span>Filter</span><strong>All</strong></div>
                <div><span>Printed</span><strong>{{ $printedAt->format('d-M-Y h:i A') }}</strong></div>
                <div><span>By</span><strong>{{ $printedBy }}</strong></div>
            </div>
        </div>
    </div>

    <div class="mini-stats ss-stats">
        <div class="mini-stat">
            <div class="label">Cash received</div>
            <div class="value" style="color:var(--green)">{{ money($summary['cash_received'], 0) }}</div>
            <div class="ss-stat-sub">Bank: {{ money($summary['bank_received'], 0) }}</div>
        </div>
        <div class="mini-stat">
            <div class="label">Expenses</div>
            <div class="value" style="color:var(--red)">{{ money($summary['total_expenses'], 0) }}</div>
            <div class="ss-stat-sub">Cash {{ money($summary['cash_expense'], 0) }} · Bank {{ money($summary['bank_expense'], 0) }}</div>
        </div>
        <div class="mini-stat ss-stat-highlight">
            <div class="label">Total earning</div>
            <div class="value" style="color:{{ $summary['total_earning'] >= 0 ? 'var(--green)' : 'var(--red)' }}">{{ money($summary['total_earning'], 0) }}</div>
            <div class="ss-stat-sub">Cash in hand {{ money($summary['cash_in_hand'], 0) }} · Bank net {{ money($summary['bank_net'], 0) }}</div>
        </div>
    </div>

    <div class="form-card" style="padding:0;overflow:hidden">
        <div class="table-wrap">
            <table class="data-table ss-table">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Voucher</th>
                        <th>Account / Detail</th>
                        <th>Payment</th>
                        <th>Details</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['no'] }}</td>
                            <td>{{ $row['date']?->format('d-M-Y') }}</td>
                            <td><span class="status-badge {{ $row['type_class'] }}">{{ $row['type'] }}</span></td>
                            <td><span class="code-pill">{{ $row['voucher'] }}</span></td>
                            <td style="font-weight:600">{{ $row['account_label'] }}</td>
                            <td>{{ $row['payment_label'] }}</td>
                            <td style="font-size:12.5px;color:var(--text-dim);max-width:280px">{{ $row['details'] }}</td>
                            <td class="num" style="font-weight:700;color:{{ $row['kind'] === 'expense' ? 'var(--red)' : 'var(--green)' }}">
                                {{ $row['kind'] === 'expense' ? '−' : '' }}{{ money($row['amount'], 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:28px;color:var(--text-dim)">No receipts or expenses in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                    <tfoot>
                        <tr>
                            <td colspan="7" class="num"><strong>Total receipts</strong></td>
                            <td class="num"><strong style="color:var(--green)">{{ money($summary['total_receipts'], 0) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="7" class="num"><strong>Total expenses</strong></td>
                            <td class="num"><strong style="color:var(--red)">−{{ money($summary['total_expenses'], 0) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="form-card ss-footer-card">
        <p class="ss-note">
            Total earning = total receipts − total expenses.
            Cash in hand = cash received − cash expense.
            Bank net = bank received − bank expense.
        </p>

        <div class="ss-signs">
            <div>
                <div class="ss-sign-line"></div>
                <div>Prepared by: {{ $printedBy }}</div>
            </div>
            <div>
                <div class="ss-sign-line"></div>
                <div>Checked by</div>
            </div>
            <div>
                <div class="ss-sign-line"></div>
                <div>Authorized signature</div>
            </div>
        </div>

        <div class="ss-foot">
            <span>{{ strtoupper($gym['name'] ?: 'FIT GENERATION') }} · Official accounts report · Do not edit after print</span>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ss-header { margin-bottom: 14px; }
.ss-top {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 18px;
}
.ss-brand { display: flex; gap: 12px; align-items: center; }
.ss-logo {
  width: 46px; height: 46px; border-radius: 11px;
  background: #fff;
  color: #fff; display: grid; place-items: center;
  font-weight: 800; letter-spacing: .04em;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  box-shadow: 0 6px 18px rgba(32, 56, 224,.28);
}
.ss-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.ss-name { font-size: 17px; font-weight: 800; letter-spacing: -.2px; color: var(--text); }
.ss-meta { font-size: 12px; color: var(--text-dim); margin-top: 3px; }
.ss-badge {
  border: 1px solid rgba(32, 56, 224,.45);
  background: rgba(32, 56, 224,.12);
  color: var(--purple-2);
  border-radius: 10px;
  padding: 10px 14px;
  text-align: center;
  min-width: 140px;
}
.ss-badge strong { display: block; font-size: 12px; letter-spacing: .06em; }
.ss-badge span { display: block; font-size: 11px; margin-top: 3px; color: var(--text-dim); }

.ss-title-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-end;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.ss-title-row h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -.3px;
  color: var(--text);
}
.ss-subtitle {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--text-dim);
}
.ss-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(140px, auto));
  gap: 6px 18px;
  font-size: 12px;
}
.ss-meta-grid span { color: var(--text-mute); margin-right: 6px; }
.ss-meta-grid strong { color: var(--text); }

.ss-stats { margin-bottom: 14px; grid-template-columns: repeat(3, 1fr); }
.ss-stat-sub {
  margin-top: 6px;
  font-size: 11.5px;
  color: var(--text-mute);
}
.ss-stat-highlight {
  border-color: rgba(34,197,94,.35) !important;
  background: linear-gradient(180deg, rgba(34,197,94,.10), transparent) !important;
}

.ss-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.ss-table tfoot td {
  border-top: 1px solid var(--border-2);
  background: rgba(32, 56, 224,.06);
  padding: 12px 14px;
}

.ss-footer-card { margin-top: 14px; }
.ss-note {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--text-dim);
  line-height: 1.5;
}
.ss-signs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
  margin-top: 22px;
  font-size: 12.5px;
  color: var(--text-dim);
}
.ss-sign-line {
  border-top: 1px dashed var(--border-2);
  margin-bottom: 8px;
  margin-top: 28px;
}
.ss-foot {
  margin-top: 18px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
  font-size: 11.5px;
  color: var(--text-mute);
}

@media (max-width: 900px) {
  .ss-top, .ss-title-row { flex-direction: column; align-items: stretch; }
  .ss-meta-grid { grid-template-columns: 1fr 1fr; }
  .ss-signs { grid-template-columns: 1fr; }
}

@media print {
  body * { visibility: hidden !important; }
  #sales-sheet, #sales-sheet * { visibility: visible !important; }
  #sales-sheet {
    position: absolute;
    left: 0; top: 0; width: 100%;
    background: #fff !important;
    color: #111 !important;
  }
  .no-print, .sidebar, .topbar, .page-toolbar, .report-tabs { display: none !important; }
  .main, .content { margin: 0 !important; padding: 0 !important; }

  .form-card, .mini-stat, .ss-badge, .ss-stat-highlight, .data-table tfoot td {
    background: #fff !important;
    border-color: #d1d5db !important;
    box-shadow: none !important;
    color: #111 !important;
  }
  .ss-logo {
    background: #111 !important;
    box-shadow: none !important;
  }
  .ss-badge { border: 2px solid #b91c1c !important; color: #b91c1c !important; }
  .ss-badge span, .ss-meta, .ss-subtitle, .ss-note, .ss-foot, .ss-signs,
  .ss-meta-grid span, .ss-stat-sub, .mini-stat .label { color: #555 !important; }
  .ss-name, .ss-title-row h1, .ss-meta-grid strong, .mini-stat .value,
  .data-table th, .data-table td, .status-badge, .code-pill { color: #111 !important; }
  .data-table th { background: #f3f4f6 !important; }
  .ss-sign-line { border-top: 1px solid #9ca3af !important; }
}
</style>
@endpush
