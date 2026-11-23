@extends('admin.layout')
@section('title', 'Import preview')

@section('content')
    @php
        $rows = collect($import['rows']);
        $skipCount = $rows->count() - $readyCount;
        $opt = $import['options'];
    @endphp
    <div class="page-head">
        <div>
            <a class="small" href="{{ route('admin.staff.import') }}">← Upload a different file</a>
            <h1 style="margin-top:4px">Check before importing</h1>
            <p>
                {{ $import['file_name'] }}: <strong>{{ $readyCount }}</strong> ready to import
                @if ($skipCount) · <strong>{{ $skipCount }}</strong> will be skipped @endif.
                Nothing has been saved yet.
            </p>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-body small">
            Every imported employee gets: status <strong>{{ ucfirst($opt['employment_status']) }}</strong>,
            branch <strong>{{ $branch ?? 'none' }}</strong>, shift <strong>{{ $shift ?? 'none' }}</strong>,
            default department <strong>{{ $department ?? 'none' }}</strong>,
            and {!! $opt['create_logins'] ? 'an app login with role <strong>'.e($opt['role']).'</strong>' : '<strong>no app login</strong>' !!}.
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Line</th><th>Employee</th><th>Code</th><th>Phone</th><th>Joining</th><th>Designation / job title</th><th>Notes</th></tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php $d = $row['data']; @endphp
                    <tr @if ($row['errors']) style="opacity:.75" @endif>
                        <td class="muted">{{ $row['line'] }}</td>
                        <td>
                            <strong>{{ trim($d['first_name'].' '.$d['last_name']) ?: '—' }}</strong>
                            <span class="sub" style="display:block">{{ $d['email'] ?: '—' }}</span>
                        </td>
                        <td class="mono">{!! $d['employee_code'] ? e($d['employee_code']) : '<span class="muted">blank</span>' !!}</td>
                        <td class="mono">{{ $d['phone'] ?? '—' }}</td>
                        <td class="nowrap">{{ $d['joining_date'] ? \Illuminate\Support\Carbon::parse($d['joining_date'])->format('d M Y') : '—' }}</td>
                        <td>{{ $d['designation'] ?? '—' }}<span class="sub" style="display:block">{{ $d['position'] }}</span></td>
                        <td class="small">
                            @if ($row['errors'])
                                <span class="badge bad">Skipped</span>
                                @foreach ($row['errors'] as $e)<div>{{ $e }}</div>@endforeach
                            @else
                                <span class="badge ok">Ready</span>
                            @endif
                            @foreach ($row['warnings'] as $w)<div class="muted">{{ $w }}</div>@endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-body actions" style="justify-content:flex-end;margin-top:16px">
        <a class="btn" href="{{ route('admin.staff.import') }}">Cancel</a>
        @if ($readyCount)
            <form method="POST" action="{{ route('admin.staff.import.store') }}" class="inline"
                  data-confirm="Create {{ $readyCount }} {{ \Illuminate\Support\Str::plural('employee', $readyCount) }}?">
                @csrf
                <button class="btn primary" type="submit">Import {{ $readyCount }} {{ \Illuminate\Support\Str::plural('employee', $readyCount) }}</button>
            </form>
        @endif
    </div>
@endsection
