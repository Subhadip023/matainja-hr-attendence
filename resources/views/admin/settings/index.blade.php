@extends('admin.layout')
@section('title', 'Settings')

@section('content')
    @php
        $enforcedRaw = $stored['attendance.geofence_enforced']->value ?? null;
        $enforced = $enforcedRaw === null || filter_var($enforcedRaw, FILTER_VALIDATE_BOOLEAN);
        $radius = $stored['attendance.geofence_radius_meters']->value ?? null;
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    @endphp
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p>Company-wide rules the mobile app and API follow.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        <div class="grid grid-2" style="align-items:start">
            <div class="card">
                <div class="card-head"><h2>Attendance & WFH</h2></div>
                <div class="card-body">
                    <div class="field">
                        <label>Work-from-home weekdays</label>
                        <div class="toolbar" style="gap:14px">
                            @foreach ($days as $n => $day)
                                <label class="check" style="font-weight:400"><input type="checkbox" name="wfh_weekdays[]" value="{{ $n }}" @checked(in_array($n, $wfhDays, true))> {{ $day }}</label>
                            @endforeach
                        </div>
                        <div class="hint">On these days everyone may check in from anywhere (no geofence) and the day is stored as WFH. Approved WFH requests work the same on any day.
                            The app's on-screen "WFH day" notice is built for Wednesday, so if you change this, update the app too.</div>
                    </div>
                    <div class="field">
                        <input type="hidden" name="geofence_enforced" value="0">
                        <label class="check"><input type="checkbox" name="geofence_enforced" value="1" @checked($enforced)> Enforce the office geofence on office days</label>
                        <div class="hint">When on, staff must be inside their branch radius to check in or out. Branches without coordinates are never enforced.</div>
                    </div>
                    <div class="field" style="max-width:260px">
                        <label for="radius">Default geofence radius (metres)</label>
                        <input id="radius" type="number" name="geofence_radius_meters" min="10" max="100000" value="{{ old('geofence_radius_meters', $radius) }}" placeholder="100">
                        <div class="hint">Used when a branch has no radius of its own. Default 100 m.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Other settings</h2></div>
                <div class="card-body">
                    @forelse ($others as $key => $setting)
                        <div class="field">
                            <label class="mono" for="s_{{ $loop->index }}">{{ $key }}</label>
                            <input id="s_{{ $loop->index }}" type="text" name="custom[{{ $key }}]" value="{{ old('custom.'.$key, $setting->value) }}">
                        </div>
                    @empty
                        <p class="muted">No other settings stored yet.</p>
                    @endforelse
                    <details>
                        <summary class="small" style="color:var(--brand);margin-bottom:10px">+ Add a setting</summary>
                        <div class="form-grid">
                            <div class="field"><label>Key</label><input type="text" name="new_key" value="{{ old('new_key') }}" placeholder="company.name"></div>
                            <div class="field"><label>Value</label><input type="text" name="new_value" value="{{ old('new_value') }}"></div>
                        </div>
                    </details>
                </div>
            </div>
        </div>
        <div class="actions" style="justify-content:flex-end;margin-top:16px">
            <button class="btn primary" type="submit">Save settings</button>
        </div>
    </form>
@endsection
