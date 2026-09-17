@extends('layouts.app')

@section('title', 'Reports')

@section('content')
@include('reports._toolbar')

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Income</div><div class="value" style="color:#22c55e">{{ money($stats['income']) }}</div></div>
    <div class="mini-stat"><div class="label">Expenses</div><div class="value" style="color:#ef4444">{{ money($stats['expenses']) }}</div></div>
    <div class="mini-stat"><div class="label">Net Profit</div><div class="value" style="color:{{ $stats['net'] >= 0 ? '#2038e0' : '#ef4444' }}">{{ money($stats['net']) }}</div></div>
    <div class="mini-stat"><div class="label">Attendance</div><div class="value">{{ number_format($stats['attendance']) }}</div></div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Members</div><div class="value">{{ number_format($stats['members']) }}</div></div>
    <div class="mini-stat"><div class="label">Active Members</div><div class="value" style="color:#22c55e">{{ number_format($stats['active_members']) }}</div></div>
    <div class="mini-stat"><div class="label">Fee Active (as of To)</div><div class="value" style="color:#22c55e">{{ number_format($stats['active_fee']) }}</div></div>
    <div class="mini-stat"><div class="label">Fee Pending (as of To)</div><div class="value" style="color:#f59e0b">{{ number_format($stats['pending_fee']) }}</div></div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">New Members (range)</div><div class="value" style="color:#3b82f6">{{ number_format($stats['new_members']) }}</div></div>
    <div class="mini-stat"><div class="label">Pending Fees</div><div class="value" style="color:#f59e0b">{{ number_format($stats['invoices_due']) }} · {{ money($stats['due_amount']) }}</div></div>
</div>

<div class="report-grid">
    <div class="form-card">
        <div class="card-head" style="margin-bottom:10px">
            <h3 style="font-size:14.5px">Income vs Expenses (6 months)</h3>
        </div>
        <div class="chart-box"><canvas id="financeChart"></canvas></div>
    </div>
    <div class="form-card">
        <div class="card-head" style="margin-bottom:10px">
            <h3 style="font-size:14.5px">Attendance (last 7 days)</h3>
        </div>
        <div class="chart-box"><canvas id="attendanceChart"></canvas></div>
    </div>
</div>

<div class="report-grid-3">
    <div class="form-card">
        <h3 style="font-size:14.5px;margin-bottom:12px">Membership Status</h3>
        <div class="chart-box" style="min-height:200px"><canvas id="membershipChart"></canvas></div>
        <div style="margin-top:8px">
            @foreach($membershipStatus as $key => $count)
                <div class="report-metric">
                    <span><span class="status-badge {{ $key }}">{{ $key }}</span></span>
                    <span class="val">{{ number_format($count) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="form-card">
        <h3 style="font-size:14.5px;margin-bottom:12px">Top Classes</h3>
        @forelse($topClasses as $class)
            @php $pct = $class->capacity > 0 ? min(100, round(($class->enrollments_count / $class->capacity) * 100)) : 0; @endphp
            <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:6px">
                    <div>
                        <div style="font-weight:600;font-size:13px">{{ $class->name }}</div>
                        <div style="font-size:11.5px;color:var(--text-mute)">{{ $class->trainer?->full_name ?? 'Unassigned' }}</div>
                    </div>
                    <div style="font-size:12px;font-weight:700">{{ $class->enrollments_count }}/{{ $class->capacity }}</div>
                </div>
                <div class="cap-bar"><div class="cap-fill purple" style="width:{{ $pct }}%"></div></div>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No classes yet.</p>
        @endforelse
        <div class="report-metric" style="margin-top:10px">
            <span>Active trainers</span><span class="val">{{ $stats['trainers'] }}</span>
        </div>
        <div class="report-metric">
            <span>Active classes</span><span class="val">{{ $stats['classes'] }}</span>
        </div>
    </div>

    <div class="form-card">
        <h3 style="font-size:14.5px;margin-bottom:12px">Payments by Method</h3>
        @forelse($paymentMethods as $row)
            <div class="report-metric">
                <span>{{ ucfirst(str_replace('_',' ',$row->method)) }} <span style="color:var(--text-mute)">({{ $row->total }})</span></span>
                <span class="val" style="color:#22c55e">{{ money($row->amount) }}</span>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px;margin-bottom:14px">No payments in range.</p>
        @endforelse

        <h3 style="font-size:14.5px;margin:18px 0 12px">Expenses by Category</h3>
        @forelse($expenseByCategory as $row)
            <div class="report-metric">
                <span>{{ $row->category }} <span style="color:var(--text-mute)">({{ $row->total }})</span></span>
                <span class="val" style="color:#ef4444">{{ money($row->amount) }}</span>
            </div>
        @empty
            <p style="color:var(--text-dim);font-size:13px">No expenses in range.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
  const symbol = @json(currency_symbol());
  const finance = @json($financeTrend);
  const attendance = @json($attendanceTrend);
  const membership = @json($membershipStatus);

  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.color = '#8b8b9e';
  Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

  new Chart(document.getElementById('financeChart'), {
    type: 'bar',
    data: {
      labels: finance.map(i => i.label),
      datasets: [
        { label: 'Income', data: finance.map(i => i.income), backgroundColor: '#22c55e', borderRadius: 5, barPercentage: 0.55, categoryPercentage: 0.65 },
        { label: 'Expenses', data: finance.map(i => i.expenses), backgroundColor: '#ef4444', borderRadius: 5, barPercentage: 0.55, categoryPercentage: 0.65 },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { labels: { boxWidth: 10, usePointStyle: true } },
        tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${symbol}${Number(c.parsed.y).toLocaleString()}` } }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, border: { display: false }, ticks: { callback: (v) => symbol + (v >= 1000 ? (v/1000)+'K' : v) } }
      }
    }
  });

  new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
      labels: attendance.map(i => i.label),
      datasets: [{
        label: 'Check-ins',
        data: attendance.map(i => i.value),
        borderColor: '#8b6cff',
        backgroundColor: 'rgba(32, 56, 224,0.2)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#0b0b12',
        pointBorderColor: '#2038e0',
        pointBorderWidth: 2,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, border: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,0.05)' }, border: { display: false } }
      }
    }
  });

  new Chart(document.getElementById('membershipChart'), {
    type: 'doughnut',
    data: {
      labels: ['Active', 'Leave', 'Pending', 'Cancelled'],
      datasets: [{
        data: [membership.active, membership.leave, membership.pending, membership.cancelled],
        backgroundColor: ['#22c55e', '#f59e0b', '#3b82f6', '#ef4444'],
        borderColor: '#14141f',
        borderWidth: 3,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: { legend: { display: false } }
    }
  });
})();
</script>
@endpush
