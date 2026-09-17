@php
    $isEdit = isset($expense) && $expense;
    $inModal = ! empty($inModal);
    $categoryList = $categories ?? ($formCategories ?? []);
@endphp
@if(! $inModal)
<div class="form-card" style="max-width:920px">
@endif
    <form method="POST" action="{{ $isEdit ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" @if($inModal) id="expense-create-form" @endif>
        @csrf
        @if($isEdit) @method('PUT') @endif
        @if($inModal)
            <input type="hidden" name="_form" value="expense_create">
        @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Category <span class="req">*</span></label>
                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                    <option value="">Select category</option>
                    @foreach($categoryList as $cat)
                        <option value="{{ $cat }}" @selected(old('category', $expense->category ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                @error('category')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Amount ({{ currency_symbol() }}) <span class="req">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $expense->amount ?? '') }}" required>
                @error('amount')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Expense Date <span class="req">*</span></label>
                <input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', isset($expense) ? $expense->expense_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label>Account</label>
                <select name="account_id" class="form-select">
                    <option value="">Select account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id', $expense->account_id ?? '') == $account->id)>
                            {{ $account->name }} ({{ $account->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method <span class="req">*</span></label>
                <select name="payment_method" class="form-select" required>
                    @foreach(['cash','card','bank_transfer','other'] as $m)
                        <option value="{{ $m }}" @selected(old('payment_method', $expense->payment_method ?? 'cash') === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Vendor</label>
                <input type="text" name="vendor" class="form-control" value="{{ old('vendor', $expense->vendor ?? '') }}">
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['pending','approved','rejected'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $expense->status ?? 'approved') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="3">{{ old('description', $expense->description ?? '') }}</textarea>
            </div>
            <div class="form-group full">
                <label>Attachment / Receipt</label>
                <input
                    type="file"
                    name="attachment"
                    id="expense-attachment{{ $inModal ? '-modal' : '' }}"
                    class="form-control @error('attachment') is-invalid @enderror"
                    accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf"
                >
                <p class="field-hint">JPG, PNG, WEBP, GIF or PDF · max 2 MB</p>
                @error('attachment')<span class="field-error">{{ $message }}</span>@enderror

                @if($isEdit && $expense->receipt_url)
                    <div class="attachment-preview" style="margin-top:12px">
                        @if($expense->receipt_is_image)
                            <img src="{{ $expense->receipt_url }}" alt="Receipt" style="max-width:220px;max-height:160px;border-radius:10px;border:1px solid var(--border);object-fit:cover">
                        @else
                            <a href="{{ $expense->receipt_url }}" target="_blank" class="btn btn-secondary btn-sm">View current file</a>
                        @endif
                        <label class="switch-row compact" style="margin-top:10px">
                            <input type="checkbox" name="remove_attachment" value="1">
                            <span><strong>Remove current attachment</strong></span>
                        </label>
                    </div>
                @endif
                <div id="attachment-new-preview{{ $inModal ? '-modal' : '' }}" style="margin-top:10px;display:none"></div>
            </div>
        </div>

        <div class="form-actions">
            @if($inModal)
                <button type="button" class="btn btn-ghost" data-expense-modal-close>Cancel</button>
            @else
                <a href="{{ route('expenses.index') }}" class="btn btn-ghost">Cancel</a>
            @endif
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Expense' : 'Save Expense' }}</button>
        </div>
    </form>
@if(! $inModal)
</div>
@endif
<script>
(function () {
  const input = document.getElementById('expense-attachment{{ $inModal ? '-modal' : '' }}');
  const box = document.getElementById('attachment-new-preview{{ $inModal ? '-modal' : '' }}');
  input?.addEventListener('change', function () {
    const file = input.files?.[0];
    if (!file || !box) return;
    if (file.size > 2 * 1024 * 1024) {
      alert('File size must be 2 MB or less.');
      input.value = '';
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }
    box.style.display = 'block';
    if (file.type.startsWith('image/')) {
      box.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="Preview" style="max-width:220px;max-height:160px;border-radius:10px;border:1px solid var(--border);object-fit:cover">';
    } else {
      box.innerHTML = '<span class="field-hint">Selected: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)</span>';
    }
  });
})();
</script>
