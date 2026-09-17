@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Expenses</h1>
        <p>Track gym operating costs and vendors</p>
    </div>
    <div class="toolbar-actions">
        @if(auth()->user()?->canExportExpenses())
            <a href="{{ route('expenses.export', request()->query()) }}" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                Export Sheet
            </a>
        @endif
        <button type="button" class="btn btn-secondary" data-category-modal-open>
            <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h10M4 17h16"/><path d="M18 10v4M16 12h4"/></svg>
            Categories
        </button>
        <button type="button" class="btn btn-primary" data-expense-modal-open>
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Expense
        </button>
    </div>
</div>

<div class="mini-stats">
    <div class="mini-stat"><div class="label">Total Expenses</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="mini-stat"><div class="label">Approved</div><div class="value" style="color:#22c55e">{{ number_format($stats['approved']) }}</div></div>
    <div class="mini-stat"><div class="label">Pending</div><div class="value" style="color:#f59e0b">{{ number_format($stats['pending']) }}</div></div>
    <div class="mini-stat"><div class="label">Approved Amount</div><div class="value" style="color:#ef4444">{{ money($stats['amount']) }}</div></div>
</div>

<form method="GET" action="{{ route('expenses.index') }}" class="filter-bar" id="filter-form">
    <div class="filter-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search category, vendor, #...">
    </div>
    <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="From date" style="max-width:150px">
    <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="To date" style="max-width:150px">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach(['pending','approved','rejected'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
    </select>
    <select name="category" class="form-select" onchange="this.form.submit()">
        <option value="">All Categories</option>
        @foreach($categories as $cat)
            <option value="{{ $cat }}" @selected(request('category')===$cat)>{{ $cat }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request()->hasAny(['search','status','category','from','to']))
        <a href="{{ route('expenses.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="table-card">
    @if($expenses->count())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Expense</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $expense)
                        <tr>
                            <td><span class="code-pill">{{ $expense->expense_number }}</span></td>
                            <td>
                                <div style="font-weight:600">{{ $expense->category }}</div>
                                <div style="font-size:11.5px;color:var(--text-mute)">{{ $expense->account?->name ?? 'No account' }}</div>
                            </td>
                            <td>{{ $expense->vendor ?: '—' }}</td>
                            <td>{{ $expense->expense_date?->format('M d, Y') }}</td>
                            <td style="font-weight:700">{{ money($expense->amount) }}</td>
                            <td><span class="status-badge {{ $expense->status }}">{{ $expense->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('expenses.show', $expense) }}" class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('expenses.edit', $expense) }}" class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                    <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Delete this expense?')">
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
        @include('partials.pagination', ['paginator' => $expenses])
    @else
        <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg></div>
            <h3>No expenses found</h3>
            <p>Log rent, utilities, salaries, and other costs.</p>
            <button type="button" class="btn btn-primary" data-expense-modal-open>Add Expense</button>
        </div>
    @endif
</div>

{{-- Create expense popup --}}
<div class="expense-modal-root {{ !empty($openCreateModal) ? 'is-open' : '' }}" id="expense-create-modal" aria-hidden="{{ !empty($openCreateModal) ? 'false' : 'true' }}">
    <div class="expense-modal-backdrop" data-expense-modal-close></div>
    <div class="expense-modal" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
        <div class="expense-modal-head">
            <div>
                <h2 id="expense-modal-title">Add Expense</h2>
                <p>Category is used as the expense name</p>
            </div>
            <button type="button" class="btn-icon" data-expense-modal-close title="Close" aria-label="Close">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="expense-modal-body">
            @include('expenses.form', [
                'expense' => null,
                'accounts' => $accounts,
                'categories' => $formCategories,
                'inModal' => true,
            ])
        </div>
    </div>
</div>

{{-- Manage categories popup --}}
<div class="expense-modal-root {{ !empty($openCategoryModal) ? 'is-open' : '' }}" id="expense-category-modal" aria-hidden="{{ !empty($openCategoryModal) ? 'false' : 'true' }}">
    <div class="expense-modal-backdrop" data-category-modal-close></div>
    <div class="expense-modal expense-modal-sm" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
        <div class="expense-modal-head">
            <div>
                <h2 id="category-modal-title">Expense Categories</h2>
                <p>Add or rename categories used on expenses</p>
            </div>
            <button type="button" class="btn-icon" data-category-modal-close title="Close" aria-label="Close">
                <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="expense-modal-body">
            <form method="POST" action="{{ route('expenses.categories.store') }}" class="category-add-form">
                @csrf
                <input type="hidden" name="_form" value="expense_category">
                <div class="form-group" style="flex:1;margin:0">
                    <label>New category</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('_form') === 'expense_category' ? old('name') : '' }}" placeholder="e.g. Cleaning" required maxlength="100">
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn btn-primary" style="align-self:end">Add</button>
            </form>

            <div class="category-list">
                @forelse($managedCategories as $cat)
                    <div class="category-row">
                        <form method="POST" action="{{ route('expenses.categories.update', $cat) }}" class="category-edit-form">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" class="form-control" value="{{ $cat->name }}" required maxlength="100">
                            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                        </form>
                        <form method="POST" action="{{ route('expenses.categories.destroy', $cat) }}" onsubmit="return confirm('Delete category {{ $cat->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-icon danger" title="Delete">
                                <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <p style="color:var(--text-dim);font-size:13px;margin:0">No categories yet. Add one above.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.expense-modal-root {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: none;
  place-items: center;
  padding: 20px;
}
.expense-modal-root.is-open { display: grid; }
.expense-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(5, 5, 12, .72);
  backdrop-filter: blur(8px);
}
.expense-modal {
  position: relative;
  width: min(860px, 100%);
  max-height: min(92vh, 920px);
  overflow: auto;
  background: linear-gradient(180deg, #1a1a28, #12121c);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 18px;
  box-shadow: 0 30px 80px rgba(0,0,0,.55);
}
.expense-modal-sm { width: min(560px, 100%); }
.expense-modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 20px 22px 14px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: #1a1a28;
  z-index: 2;
}
.expense-modal-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}
.expense-modal-head p {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--text-dim);
}
.expense-modal-body { padding: 18px 22px 22px; }
.expense-modal-body .form-actions {
  position: sticky;
  bottom: 0;
  background: linear-gradient(180deg, transparent, #12121c 28%);
  padding-top: 14px;
  margin-top: 8px;
}
.category-add-form {
  display: flex;
  gap: 10px;
  align-items: end;
  margin-bottom: 16px;
}
.category-list { display: flex; flex-direction: column; gap: 8px; }
.category-row {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: rgba(255,255,255,.02);
}
.category-edit-form {
  display: flex;
  gap: 8px;
  flex: 1;
  align-items: center;
}
body.expense-modal-open { overflow: hidden; }
</style>
@endpush

@push('scripts')
<script>
(function () {
  function bindModal(rootId, openSelector, closeSelector, openFlag, clearParams) {
    const modal = document.getElementById(rootId);
    if (!modal) return;

    function openModal() {
      document.querySelectorAll('.expense-modal-root.is-open').forEach((m) => {
        if (m !== modal) {
          m.classList.remove('is-open');
          m.setAttribute('aria-hidden', 'true');
        }
      });
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('expense-modal-open');
      const focusEl = modal.querySelector('select[name="category"], input[name="name"], input[name="title"]');
      focusEl?.focus();
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      if (!document.querySelector('.expense-modal-root.is-open')) {
        document.body.classList.remove('expense-modal-open');
      }
      if (window.history.replaceState) {
        const url = new URL(window.location.href);
        (clearParams || []).forEach((p) => url.searchParams.delete(p));
        window.history.replaceState({}, '', url.pathname + url.search);
      }
    }

    document.querySelectorAll(openSelector).forEach((btn) => btn.addEventListener('click', openModal));
    modal.querySelectorAll(closeSelector).forEach((el) => el.addEventListener('click', closeModal));

    return { openModal, closeModal, modal };
  }

  const expenseUi = bindModal('expense-create-modal', '[data-expense-modal-open]', '[data-expense-modal-close]', null, ['create']);
  const categoryUi = bindModal('expense-category-modal', '[data-category-modal-open]', '[data-category-modal-close]', null, ['categories']);

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.expense-modal-root.is-open').forEach((m) => {
      m.classList.remove('is-open');
      m.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('expense-modal-open');
  });

  @if(!empty($openCreateModal))
  expenseUi?.openModal();
  @endif
  @if(!empty($openCategoryModal))
  categoryUi?.openModal();
  @endif
})();
</script>
@endpush
