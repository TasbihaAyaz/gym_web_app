@php
    $isEdit = isset($class) && $class;
    $scheduleRows = old('schedules', $isEdit ? $class->schedules->map(fn ($s) => [
        'id' => $s->id,
        'schedule_date' => $s->schedule_date?->format('Y-m-d'),
        'start_time' => substr((string) $s->start_time, 0, 5),
        'end_time' => substr((string) $s->end_time, 0, 5),
        'room' => $s->room,
        'status' => $s->status,
    ])->values()->all() : [[
        'schedule_date' => now()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'room' => '',
        'status' => 'scheduled',
    ]]);
@endphp
<div class="form-card" style="max-width:980px">
    <form method="POST" action="{{ $isEdit ? route('classes.update', $class) : route('classes.store') }}" enctype="multipart/form-data">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="upload-row">
            <div class="upload-preview square" id="image-preview">
                @if($isEdit && $class->image_url)
                    <img src="{{ $class->image_url }}" alt="{{ $class->name }}">
                @elseif($isEdit && $class->trainer?->avatar_url)
                    <img src="{{ $class->trainer->avatar_url }}" alt="{{ $class->name }}">
                @else
                    <span class="upload-initials">CL</span>
                @endif
            </div>
            <div class="upload-fields">
                <label>Class Image</label>
                <input type="file" name="image" id="image-input" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                <p class="field-hint">Shown on dashboard popular classes. Falls back to trainer photo if empty.</p>
                @error('image')<span class="field-error">{{ $message }}</span>@enderror
                @if($isEdit && $class->image)
                    <label class="switch-row compact" style="margin-top:10px">
                        <input type="checkbox" name="remove_image" value="1">
                        <span><strong>Remove current image</strong></span>
                    </label>
                @endif
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Class Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $class->name ?? '') }}" required>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Trainer</label>
                <select name="trainer_id" class="form-select searchable" data-placeholder="Unassigned" data-search-placeholder="Search trainer...">
                    <option value="">Unassigned</option>
                    @foreach($trainers as $trainer)
                        <option value="{{ $trainer->id }}" @selected(old('trainer_id', $class->trainer_id ?? '') == $trainer->id)>{{ $trainer->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Capacity <span class="req">*</span></label>
                <input type="number" name="capacity" min="1" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $class->capacity ?? 20) }}" required>
                @error('capacity')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Duration (minutes) <span class="req">*</span></label>
                <input type="number" name="duration_minutes" min="15" class="form-control" value="{{ old('duration_minutes', $class->duration_minutes ?? 60) }}" required>
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['active','inactive'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $class->status ?? 'active') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" class="form-textarea" rows="4">{{ old('description', $class->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="card-head" style="margin:22px 0 12px">
            <div>
                <h3 style="font-size:15px">Class Schedule</h3>
                <p style="font-size:12.5px;color:var(--text-dim);margin-top:3px">Add one or more sessions for this class</p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="add-schedule-row">Add Session</button>
        </div>

        <div id="schedule-rows" class="schedule-rows">
            @foreach($scheduleRows as $i => $row)
                <div class="schedule-row form-grid">
                    @if(!empty($row['id']))
                        <input type="hidden" name="schedules[{{ $i }}][id]" value="{{ $row['id'] }}">
                    @endif
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="schedules[{{ $i }}][schedule_date]" class="form-control" value="{{ $row['schedule_date'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Start</label>
                        <input type="time" name="schedules[{{ $i }}][start_time]" class="form-control" value="{{ $row['start_time'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>End</label>
                        <input type="time" name="schedules[{{ $i }}][end_time]" class="form-control" value="{{ $row['end_time'] ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>Room</label>
                        <input type="text" name="schedules[{{ $i }}][room]" class="form-control" value="{{ $row['room'] ?? '' }}" placeholder="Studio A">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="schedules[{{ $i }}][status]" class="form-select">
                            @foreach(['scheduled','completed','cancelled'] as $st)
                                <option value="{{ $st }}" @selected(($row['status'] ?? 'scheduled') === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="button" class="btn btn-ghost btn-sm remove-schedule">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="form-actions">
            <a href="{{ route('classes.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Class' : 'Save Class' }}</button>
        </div>
    </form>
</div>
<template id="schedule-row-template">
    <div class="schedule-row form-grid">
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="schedules[__I__][schedule_date]" class="form-control" value="{{ now()->format('Y-m-d') }}">
        </div>
        <div class="form-group">
            <label>Start</label>
            <input type="time" name="schedules[__I__][start_time]" class="form-control" value="09:00">
        </div>
        <div class="form-group">
            <label>End</label>
            <input type="time" name="schedules[__I__][end_time]" class="form-control" value="10:00">
        </div>
        <div class="form-group">
            <label>Room</label>
            <input type="text" name="schedules[__I__][room]" class="form-control" placeholder="Studio A">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="schedules[__I__][status]" class="form-select">
                <option value="scheduled" selected>Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end">
            <button type="button" class="btn btn-ghost btn-sm remove-schedule">Remove</button>
        </div>
    </div>
</template>
<script>
(function () {
    const wrap = document.getElementById('schedule-rows');
    const tpl = document.getElementById('schedule-row-template');
    let idx = {{ count($scheduleRows) }};

    document.getElementById('add-schedule-row')?.addEventListener('click', function () {
        const html = tpl.innerHTML.replaceAll('__I__', String(idx++));
        wrap.insertAdjacentHTML('beforeend', html);
    });

    wrap?.addEventListener('click', function (e) {
        if (e.target.closest('.remove-schedule')) {
            const row = e.target.closest('.schedule-row');
            if (wrap.querySelectorAll('.schedule-row').length > 1) row.remove();
        }
    });

    document.getElementById('image-input')?.addEventListener('change', function (e) {
        const file = e.target.files?.[0];
        const box = document.getElementById('image-preview');
        if (!file || !box) return;
        box.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="Preview">';
    });
})();
</script>
