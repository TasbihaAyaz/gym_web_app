@php $isEdit = isset($plan) && $plan; @endphp
<div class="form-card" style="max-width:920px">
    <form method="POST" action="{{ $isEdit ? route('plans.update', $plan) : route('plans.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Plan Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $plan->name ?? '') }}" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Tier <span class="req">*</span></label>
                <select name="tier" class="form-select" required>
                    @foreach(['basic','standard','premium'] as $tier)
                        <option value="{{ $tier }}" @selected(old('tier', $plan->tier ?? 'basic') === $tier)>{{ ucfirst($tier) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Price ({{ currency_symbol() }}) <span class="req">*</span></label>
                <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plan->price ?? '') }}" required>
                @error('price')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Duration (days) <span class="req">*</span></label>
                <input type="number" min="1" name="duration_days" class="form-control" value="{{ old('duration_days', $plan->duration_days ?? 30) }}" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" @selected(old('is_active', $plan->is_active ?? true) == 1 || old('is_active', $plan->is_active ?? true) === true)>Active</option>
                    <option value="0" @selected(old('is_active', isset($plan) ? (int)$plan->is_active : 1) === 0)>Inactive</option>
                </select>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="3">{{ old('description', $plan->description ?? '') }}</textarea>
            </div>
            <div class="form-group full">
                <label>Features</label>
                <textarea name="features_text" class="form-textarea" rows="5" placeholder="One feature per line">{{ old('features_text', isset($plan) ? implode("\n", $plan->features ?? []) : '') }}</textarea>
                <span class="form-hint">Enter one feature per line. These appear on the plan card.</span>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('plans.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Plan' : 'Save Plan' }}</button>
        </div>
    </form>
</div>
