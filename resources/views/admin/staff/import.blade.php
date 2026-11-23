@extends('admin.layout')
@section('title', 'Import employees')

@section('content')
    <div class="page-head">
        <div>
            <a class="small" href="{{ route('admin.staff.index') }}">← Employees</a>
            <h1 style="margin-top:4px">Import employees from CSV</h1>
            <p>Upload a CSV (for example a Google Form export). You'll see a preview before anything is saved.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.staff.import.template') }}">Download template</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.staff.import.upload') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-2" style="align-items:start">
            <div class="card">
                <div class="card-head"><h2>File</h2></div>
                <div class="card-body">
                    <div class="field">
                        <label for="file">CSV file *</label>
                        <input id="file" type="file" name="file" accept=".csv,text/csv" required>
                        @error('file')<div class="error">{{ $message }}</div>@enderror
                        <div class="hint">
                            Columns are matched by name: <strong>First name</strong> and <strong>Work email</strong> are required;
                            Last name, Phone No., Employee code, Joining date, Designation, Job title and Department are optional.
                        </div>
                    </div>
                    <div class="hint" style="margin-top:8px">
                        Missing or placeholder codes ("N/A", "Null") are left blank; add them later on the employee's profile. Dates like 19.06.2025, 17/12/22, 2026-03-04 or
                        "9th October 2026" are read day-first. New designations are created. Rows whose email or code already exists are skipped.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Apply to every imported employee</h2></div>
                <div class="card-body form-grid">
                    <div class="field wide">
                        <input type="hidden" name="create_logins" value="0">
                        <label class="check"><input type="checkbox" name="create_logins" value="1" @checked(old('create_logins', true))> Create mobile app logins</label>
                        <div class="hint">Each employee signs in with their work email and a generated password. You can download the list after the import.</div>
                    </div>
                    <div class="field">
                        <label for="role">App role</label>
                        <select id="role" name="role">
                            @foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', 'Employee') === $r)>{{ $r }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="employment_status">Status</label>
                        <select id="employment_status" name="employment_status">
                            @foreach ($statuses as $st)<option value="{{ $st }}" @selected(old('employment_status', 'active') === $st)>{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="branch_id">Branch</label>
                        <select id="branch_id" name="branch_id">
                            <option value="">None</option>
                            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $branches->first()?->id) == $b->id)>{{ $b->name }}</option>@endforeach
                        </select>
                        <div class="hint">Sets the office geofence used for check-in.</div>
                    </div>
                    <div class="field">
                        <label for="shift_id">Shift</label>
                        <select id="shift_id" name="shift_id">
                            <option value="">None</option>
                            @foreach ($shifts as $s)<option value="{{ $s->id }}" @selected(old('shift_id', $shifts->first()?->id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">None</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                        <div class="hint">Used when the file has no Department column or the name doesn't match.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-body actions" style="justify-content:flex-end;margin-top:16px">
            <a class="btn" href="{{ route('admin.staff.index') }}">Cancel</a>
            <button class="btn primary" type="submit">Preview import</button>
        </div>
    </form>
@endsection
