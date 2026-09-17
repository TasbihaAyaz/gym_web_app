@php
    $isEdit = isset($attendance) && $attendance;
    $checkIn = old('check_in', isset($attendance) && $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('H:i') : now()->format('H:i'));
    $checkOut = old('check_out', isset($attendance) && $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('H:i') : '');
@endphp
<div class="form-card" style="max-width:820px">
    <form method="POST" action="{{ $isEdit ? route('attendance.update', $attendance) : route('attendance.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Member <span class="req">*</span></label>
                <select name="member_id" class="form-select searchable @error('member_id') is-invalid @enderror" data-placeholder="Select member" data-search-placeholder="Search name or member code..." required>
                    <option value="">Select member</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id', $attendance->member_id ?? '') == $member->id)>
                            {{ $member->full_name }} ({{ $member->member_code }})
                        </option>
                    @endforeach
                </select>
                @error('member_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Date <span class="req">*</span></label>
                <input type="date" name="attendance_date" class="form-control @error('attendance_date') is-invalid @enderror" value="{{ old('attendance_date', isset($attendance) ? $attendance->attendance_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
                @error('attendance_date')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Check In</label>
                <input type="time" name="check_in" class="form-control" value="{{ $checkIn }}">
            </div>
            <div class="form-group">
                <label>Check Out</label>
                <input type="time" name="check_out" class="form-control @error('check_out') is-invalid @enderror" value="{{ $checkOut }}">
                @error('check_out')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Method <span class="req">*</span></label>
                <select name="method" class="form-select" required>
                    @foreach(['manual','biometric','app'] as $m)
                        <option value="{{ $m }}" @selected(old('method', $attendance->method ?? 'manual') === $m)>{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" class="form-textarea" rows="3">{{ old('notes', $attendance->notes ?? '') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Attendance' : 'Save Check In' }}</button>
        </div>
    </form>
</div>
