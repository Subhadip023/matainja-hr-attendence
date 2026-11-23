@extends('admin.layout')
@section('title', $staff->exists ? 'Edit employee' : 'Add employee')

@section('content')
    @php
        $v = fn ($field, $default = null) => old($field, $staff->{$field} ?? $default);
        $joining = old('joining_date', $staff->joining_date?->format('Y-m-d'));
        $hasLogin = (bool) $staff->user;
    @endphp

    <div class="page-head">
        <div>
            <a class="small" href="{{ route('admin.staff.index') }}">← Employees</a>
            <h1 style="margin-top:4px">{{ $staff->exists ? $staff->full_name : 'Add employee' }}</h1>
            <p>{{ $staff->exists ? 'Update details, reporting line, shift and app access.' : 'Create the employee record and, optionally, their mobile app login.' }}</p>
        </div>
        @if ($staff->exists)
            <div class="actions">
                <a class="btn" href="{{ route('admin.attendance.index', ['q' => $staff->employee_code ?: $staff->full_name]) }}">Attendance</a>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}">
        @csrf
        @if ($staff->exists) @method('PUT') @endif

        <div class="grid grid-2" style="align-items:start">
            <div class="stack">
                <div class="card">
                    <div class="card-head"><h2>Personal details</h2></div>
                    <div class="card-body form-grid">
                        <div class="field">
                            <label for="first_name">First name *</label>
                            <input id="first_name" type="text" name="first_name" value="{{ $v('first_name') }}" required>
                        </div>
                        <div class="field">
                            <label for="last_name">Last name</label>
                            <input id="last_name" type="text" name="last_name" value="{{ $v('last_name') }}">
                        </div>
                        <div class="field">
                            <label for="email">Work email *</label>
                            <input id="email" type="email" name="email" value="{{ $v('email') }}" required>
                            @if ($hasLogin)<div class="hint">Also their app login email.</div>@endif
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input id="phone" type="text" name="phone" value="{{ $v('phone') }}">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h2>Employment</h2></div>
                    <div class="card-body form-grid">
                        <div class="field">
                            <label for="employee_code">Employee code</label>
                            <input id="employee_code" type="text" name="employee_code" value="{{ old('employee_code', $staff->employee_code ?? $nextCode) }}">
                            @if ($staff->exists && ! $staff->employee_code)<div class="hint">No code yet. Add it when the ID card is issued.</div>@endif
                        </div>
                        <div class="field">
                            <label for="joining_date">Joining date</label>
                            <input id="joining_date" type="date" name="joining_date" value="{{ $joining }}">
                        </div>
                        <div class="field">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id">
                                <option value="">—</option>
                                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected($v('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="designation_id">Designation</label>
                            <select id="designation_id" name="designation_id">
                                <option value="">—</option>
                                @foreach ($designations as $d)<option value="{{ $d->id }}" @selected($v('designation_id') == $d->id)>{{ $d->title }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="position">Job title</label>
                            <input id="position" type="text" name="position" value="{{ $v('position') }}" placeholder="e.g. Flutter Developer">
                        </div>
                        <div class="field">
                            <label for="employment_status">Status *</label>
                            <select id="employment_status" name="employment_status">
                                @foreach ($statuses as $st)<option value="{{ $st }}" @selected($v('employment_status', 'active') === $st)>{{ ucfirst($st) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="branch_id">Branch</label>
                            <select id="branch_id" name="branch_id">
                                <option value="">—</option>
                                @foreach ($branches as $b)<option value="{{ $b->id }}" @selected($v('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach
                            </select>
                            <div class="hint">Sets the office geofence used for check-in.</div>
                        </div>
                        <div class="field">
                            <label for="shift_id">Shift</label>
                            <select id="shift_id" name="shift_id">
                                <option value="">—</option>
                                @foreach ($shifts as $sh)<option value="{{ $sh->id }}" @selected(old('shift_id', $currentShift) == $sh->id)>{{ $sh->name }} ({{ substr($sh->start_time, 0, 5) }}–{{ substr($sh->end_time, 0, 5) }})</option>@endforeach
                            </select>
                            @if ($staff->exists)<div class="hint">Changing it starts the new shift from today.</div>@endif
                        </div>
                        <div class="field">
                            <label for="team_id">Team</label>
                            <select id="team_id" name="team_id">
                                <option value="">—</option>
                                @foreach ($teams as $t)<option value="{{ $t->id }}" @selected($v('team_id') == $t->id)>{{ $t->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="manager_id">Reports to</label>
                            <select id="manager_id" name="manager_id">
                                <option value="">—</option>
                                @foreach ($managers as $m)<option value="{{ $m->id }}" @selected($v('manager_id') == $m->id)>{{ $m->name_with_code }}</option>@endforeach
                            </select>
                            <div class="hint">The manager approves their leave and WFH in the app.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-head"><h2>Mobile app access</h2>
                        @if ($hasLogin)<span class="badge ok">Login active</span>@else<span class="badge">No login</span>@endif
                    </div>
                    <div class="card-body">
                        @if (! $hasLogin)
                            <div class="field">
                                <label class="check"><input type="checkbox" name="create_login" value="1" id="create_login" @checked(old('create_login', ! $staff->exists))> Create an app login for this employee</label>
                            </div>
                        @else
                            <dl class="kv" style="margin:0 0 14px">
                                <dt>Login email</dt><dd>{{ $staff->user->email }}</dd>
                                <dt>Roles</dt><dd>@foreach ($staff->user->roles as $r)<span class="badge brand">{{ $r->name }}</span> @endforeach</dd>
                            </dl>
                        @endif
                        <div class="field">
                            <label for="role">Role</label>
                            <select id="role" name="role" @disabled($hasLogin && $staff->user_id === auth()->id())>
                                @foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', $currentRole ?? 'Employee') === $r)>{{ $r }}</option>@endforeach
                            </select>
                            <div class="hint">Employee: own attendance & leave. Manager: also their team's approvals. HR Admin: everyone. Super Admin: everything incl. this console.</div>
                        </div>
                        <div class="field">
                            <label for="password">{{ $hasLogin ? 'Set a new password' : 'Password' }}</label>
                            <input id="password" type="text" name="password" autocomplete="new-password" minlength="8" placeholder="{{ $hasLogin ? 'Leave empty to keep the current one' : 'Leave empty to generate one' }}">
                            <div class="hint">At least 8 characters. Share it with the employee; they can sign in to the app right away.</div>
                        </div>
                        @if ($hasLogin && $staff->initial_password)
                            <details>
                                <summary class="small" style="color:var(--brand)">Show last password set by admin</summary>
                                <p class="mono" style="margin:6px 0 0">{{ $staff->initial_password }}</p>
                            </details>
                        @endif
                    </div>
                </div>

                @if ($staff->exists && $staff->shiftAssignments->isNotEmpty())
                    <div class="card">
                        <div class="card-head"><h2>Shift history</h2></div>
                        <ul class="list">
                            @foreach ($staff->shiftAssignments->sortByDesc('effective_from') as $a)
                                <li><span>{{ $a->shift?->name }}</span><span class="muted small">{{ \Illuminate\Support\Carbon::parse($a->effective_from)->format('d M Y') }} → {{ $a->effective_to ? \Illuminate\Support\Carbon::parse($a->effective_to)->format('d M Y') : 'now' }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-body actions" style="justify-content:flex-end">
                    <a class="btn" href="{{ route('admin.staff.index') }}">Cancel</a>
                    <button class="btn primary" type="submit">{{ $staff->exists ? 'Save changes' : 'Add employee' }}</button>
                </div>
            </div>
        </div>
    </form>

    @if ($staff->exists && $staff->user_id !== auth()->id())
        @php $blockers = $staff->deletionBlockers(); @endphp
        <div class="card" id="delete" style="margin-top:16px;border-color:#f1c3bd">
            <div class="card-head"><h2>Delete employee</h2></div>
            <div class="card-body">
                @if ($blockers === [])
                    <p style="margin:0 0 12px">Permanently removes {{ $staff->full_name }}{{ $hasLogin ? ' and their app login' : '' }}. Use this for someone added by mistake. This can't be undone.</p>
                    <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}" data-confirm="Permanently delete {{ $staff->full_name }}{{ $hasLogin ? ' and their app login' : '' }}? This can't be undone.">
                        @csrf @method('DELETE')
                        <button class="btn danger" type="submit">Delete employee</button>
                    </form>
                @else
                    <p style="margin:0 0 8px">
                        {{ $staff->full_name }} has <strong>{{ implode(', ', $blockers) }}</strong>.
                        Normally you should use <strong>Deactivate</strong> on the Employees list; it signs them out and keeps their records.
                    </p>
                    <p style="margin:0 0 12px">
                        To remove them anyway (for example a test account), force delete. This permanently erases {{ $staff->full_name }}{{ $hasLogin ? ', their app login' : '' }} and all of the history above. It can't be undone.
                    </p>
                    <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}" data-confirm="Force delete {{ $staff->full_name }} and erase {{ implode(', ', $blockers) }}? This can't be undone.">
                        @csrf @method('DELETE')
                        <input type="hidden" name="force" value="1">
                        <div class="field" style="max-width:320px;margin-bottom:12px">
                            <label for="confirm_code">Type <strong>{{ $staff->deleteConfirmationValue() }}</strong> to confirm</label>
                            <input id="confirm_code" type="text" name="confirm_code" autocomplete="off" required>
                        </div>
                        <button class="btn danger" type="submit">Force delete employee</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
