{{-- Shared report toolbar: date range + report type tabs --}}
<div class="page-toolbar">
    <div>
        <h1>Reports</h1>
        <p>
            {{ $reportTabs[$report] ?? 'Reports' }}
            · {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} → {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
        </p>
    </div>
    <div class="toolbar-actions" style="flex-wrap:wrap;gap:8px">
        @if($report !== 'overview' && $report !== 'earnings')
            <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                Export CSV
            </a>
        @endif
        @if(($report ?? '') !== 'earnings')
            <form method="GET" action="{{ route('reports.index') }}" class="toolbar-actions" style="gap:8px;flex-wrap:wrap">
                <input type="hidden" name="report" value="{{ $report }}">
                <input type="date" name="from" class="form-control" style="width:auto" value="{{ $from }}" title="From date">
                <input type="date" name="to" class="form-control" style="width:auto" value="{{ $to }}" title="To date">
                <button type="submit" class="btn btn-primary">Apply</button>
            </form>
        @endif
    </div>
</div>

<div class="report-tabs" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px">
    @foreach($reportTabs as $key => $label)
        <a href="{{ route('reports.index', ['report' => $key, 'from' => $from, 'to' => $to]) }}"
           class="btn {{ $report === $key ? 'btn-primary' : 'btn-secondary' }} btn-sm">
            {{ $label }}
        </a>
    @endforeach
</div>
