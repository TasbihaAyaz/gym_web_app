@php $isEdit = isset($account) && $account; @endphp
<div class="form-card" style="max-width:820px">
    <form method="POST" action="{{ $isEdit ? route('accounts.update', $account) : route('accounts.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Account Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $account->name ?? '') }}" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Code <span class="req">*</span></label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $account->code ?? '') }}" required>
                @error('code')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Type <span class="req">*</span></label>
                <select name="type" class="form-select" required>
                    @foreach(['asset','liability','income','expense','equity'] as $type)
                        <option value="{{ $type }}" @selected(old('type', $account->type ?? 'asset') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Opening Balance ({{ currency_symbol() }}) <span class="req">*</span></label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" @selected(old('is_active', $account->is_active ?? true) == 1 || old('is_active', $account->is_active ?? true) === true)>Active</option>
                    <option value="0" @selected(old('is_active', isset($account) ? (int) $account->is_active : 1) === 0)>Inactive</option>
                </select>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="3">{{ old('description', $account->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('accounts.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Account' : 'Save Account' }}</button>
        </div>
    </form>
</div>
