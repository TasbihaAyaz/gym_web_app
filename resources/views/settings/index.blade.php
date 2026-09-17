@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="page-toolbar">
    <div>
        <h1>Settings</h1>
        <p>Configure gym preferences, finance defaults, and notifications</p>
    </div>
</div>

<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    @method('PUT')

    <div class="settings-layout">
        <aside class="settings-nav form-card">
            @foreach($groups as $key => $meta)
                <a href="#group-{{ $key }}" class="settings-nav-item">
                    <strong>{{ $meta['label'] }}</strong>
                    <span>{{ $meta['desc'] }}</span>
                </a>
            @endforeach
        </aside>

        <div class="settings-panels">
            @foreach($groups as $groupKey => $meta)
                @php $fields = $grouped[$groupKey] ?? collect(); @endphp
                <section class="form-card settings-panel" id="group-{{ $groupKey }}">
                    <div class="card-head" style="margin-bottom:18px">
                        <div>
                            <h3 style="font-size:15px">{{ $meta['label'] }}</h3>
                            <p style="font-size:12.5px;color:var(--text-dim);margin-top:3px">{{ $meta['desc'] }}</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        @foreach($fields as $field)
                            <div class="form-group {{ in_array($field['type'], ['text', 'boolean'], true) || in_array($field['key'], ['gym_address'], true) ? 'full' : '' }}">
                                @if($field['type'] === 'boolean')
                                    <label class="switch-row">
                                        <input type="checkbox" name="settings[{{ $field['key'] }}]" value="1" @checked((string) $field['value'] === '1')>
                                        <span>
                                            <strong>{{ $field['label'] }}</strong>
                                            <small>Enable or disable this notification</small>
                                        </span>
                                    </label>
                                @elseif($field['type'] === 'text')
                                    <label>{{ $field['label'] }}</label>
                                    <textarea name="settings[{{ $field['key'] }}]" class="form-textarea" rows="3">{{ old('settings.'.$field['key'], $field['value']) }}</textarea>
                                @elseif($field['key'] === 'attendance_method')
                                    <label>{{ $field['label'] }}</label>
                                    <select name="settings[{{ $field['key'] }}]" class="form-select">
                                        @foreach(['manual','biometric','app'] as $m)
                                            <option value="{{ $m }}" @selected(old('settings.'.$field['key'], $field['value']) === $m)>{{ ucfirst($m) }}</option>
                                        @endforeach
                                    </select>
                                @elseif($field['key'] === 'timezone')
                                    <label>{{ $field['label'] }}</label>
                                    <select name="settings[{{ $field['key'] }}]" class="form-select">
                                        @foreach(['Asia/Karachi','Asia/Dubai','UTC','Asia/Kolkata','Europe/London'] as $tz)
                                            <option value="{{ $tz }}" @selected(old('settings.'.$field['key'], $field['value']) === $tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <label>{{ $field['label'] }}</label>
                                    <input
                                        type="{{ $field['type'] === 'number' ? 'number' : (str_contains($field['key'], 'time') ? 'time' : 'text') }}"
                                        name="settings[{{ $field['key'] }}]"
                                        class="form-control @error('settings.'.$field['key']) is-invalid @enderror"
                                        value="{{ old('settings.'.$field['key'], $field['value']) }}"
                                        @if($field['type'] === 'number') step="any" @endif
                                    >
                                @endif
                                @error('settings.'.$field['key'])<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="form-actions" style="border:none;padding:0;margin-top:4px">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>
</form>
@endsection
