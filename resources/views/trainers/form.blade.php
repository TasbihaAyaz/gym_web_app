@php $isEdit = isset($trainer) && $trainer; @endphp
<div class="form-card" style="max-width:920px">
    <form method="POST" action="{{ $isEdit ? route('trainers.update', $trainer) : route('trainers.store') }}" enctype="multipart/form-data">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="upload-row">
            <div class="upload-preview" id="avatar-preview">
                @if($isEdit && $trainer->avatar_url)
                    <img src="{{ $trainer->avatar_url }}" alt="{{ $trainer->full_name }}">
                @else
                    <span class="upload-initials">{{ $isEdit ? $trainer->initials : 'TR' }}</span>
                @endif
            </div>
            <div class="upload-fields">
                <label>Trainer Photo</label>
                <input type="file" name="avatar" id="avatar-input" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                <p class="field-hint">Used on class cards and trainer profiles. JPG/PNG/WEBP · max 2MB.</p>
                @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                @if($isEdit && $trainer->avatar)
                    <label class="switch-row compact" style="margin-top:10px">
                        <input type="checkbox" name="remove_avatar" value="1">
                        <span><strong>Remove current photo</strong></span>
                    </label>
                @endif
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $trainer->first_name ?? '') }}" required>
                @error('first_name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Last Name <span class="req">*</span></label>
                <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $trainer->last_name ?? '') }}" required>
                @error('last_name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $trainer->email ?? '') }}">
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $trainer->phone ?? '') }}">
            </div>
            <div class="form-group">
                <label>Specialization</label>
                <input type="text" name="specialization" class="form-control" value="{{ old('specialization', $trainer->specialization ?? '') }}" placeholder="e.g. Strength, Yoga, HIIT">
            </div>
            <div class="form-group">
                <label>Hourly Rate ({{ currency_symbol() }})</label>
                <input type="number" step="0.01" min="0" name="hourly_rate" class="form-control" value="{{ old('hourly_rate', $trainer->hourly_rate ?? '') }}">
            </div>
            <div class="form-group">
                <label>Hire Date</label>
                <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date', isset($trainer) && $trainer->hire_date ? $trainer->hire_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['active','inactive'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $trainer->status ?? 'active') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Bio</label>
                <textarea name="bio" class="form-textarea" rows="4">{{ old('bio', $trainer->bio ?? '') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('trainers.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Trainer' : 'Save Trainer' }}</button>
        </div>
    </form>
</div>
<script>
document.getElementById('avatar-input')?.addEventListener('change', function (e) {
    const file = e.target.files?.[0];
    const box = document.getElementById('avatar-preview');
    if (!file || !box) return;
    box.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="Preview">';
});
</script>
