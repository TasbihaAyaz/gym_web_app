@php $isEdit = isset($user) && $user; @endphp
<div class="form-card" style="max-width:920px">
    <form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name ?? '') }}" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email ?? '') }}" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone ?? '') }}" placeholder="+92 300 0000000">
                @error('phone')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role_id" class="form-select @error('role_id') is-invalid @enderror">
                    <option value="">No role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id ?? '') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['active','inactive'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $user->status ?? 'active') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
                @error('status')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Password @if(!$isEdit)<span class="req">*</span>@endif</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $isEdit ? '' : 'required' }} autocomplete="new-password" placeholder="{{ $isEdit ? 'Leave blank to keep current' : 'Min. 6 characters' }}">
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Confirm Password @if(!$isEdit)<span class="req">*</span>@endif</label>
                <input type="password" name="password_confirmation" class="form-control" {{ $isEdit ? '' : 'required' }} autocomplete="new-password">
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('users.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update User' : 'Save User' }}</button>
        </div>
    </form>
</div>
