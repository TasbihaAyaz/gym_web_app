@php
    $isEdit = isset($role) && $role;
    $selected = old('permissions', $selected ?? []);
@endphp
<div class="form-card" style="max-width:1080px">
    <form method="POST" action="{{ $isEdit ? route('roles.update', $role) : route('roles.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Role Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role->name ?? '') }}" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $role->slug ?? '') }}" placeholder="Auto-generated from name" @disabled(($role->slug ?? '') === 'admin')>
                @if(($role->slug ?? '') === 'admin')
                    <input type="hidden" name="slug" value="admin">
                @endif
                @error('slug')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="2" placeholder="What this role can do">{{ old('description', $role->description ?? '') }}</textarea>
                @error('description')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group full">
                <label class="switch-row compact">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $role->is_active ?? true))>
                    <span>
                        <strong>Active role</strong>
                        <small>Inactive roles cannot be assigned to new users</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="perm-section">
            <div class="card-head" style="margin:22px 0 14px">
                <div>
                    <h3 style="font-size:15px">Permissions</h3>
                    <p style="font-size:12.5px;color:var(--text-dim);margin-top:3px">Select module actions this role can perform</p>
                </div>
                <div class="toolbar-actions">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm-check').forEach(c => c.checked = true)">Select all</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm-check').forEach(c => c.checked = false)">Clear</button>
                </div>
            </div>

            @error('permissions')<span class="field-error" style="display:block;margin-bottom:10px">{{ $message }}</span>@enderror
            @error('permissions.*')<span class="field-error" style="display:block;margin-bottom:10px">{{ $message }}</span>@enderror

            <div class="perm-grid">
                @forelse($permissionGroups as $module => $perms)
                    <div class="perm-card">
                        <div class="perm-card-head">
                            <strong>{{ ucfirst(str_replace('_', ' ', $module)) }}</strong>
                            <button type="button" class="btn-link" onclick="this.closest('.perm-card').querySelectorAll('.perm-check').forEach(c => c.checked = true)">All</button>
                        </div>
                        <div class="perm-checks">
                            @foreach($perms as $perm)
                                <label class="perm-check-row">
                                    <input type="checkbox" class="perm-check" name="permissions[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $selected, true) || in_array((string) $perm->id, $selected, true))>
                                    <span>{{ $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p style="color:var(--text-dim);font-size:13px">No permissions seeded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('roles.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Role' : 'Save Role' }}</button>
        </div>
    </form>
</div>
