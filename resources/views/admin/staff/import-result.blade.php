@extends('admin.layout')
@section('title', 'Import finished')

@section('content')
    @php
        $created = collect($result['created']);
        $withLogin = $created->whereNotNull('password');
    @endphp
    <div class="page-head">
        <div>
            <a class="small" href="{{ route('admin.staff.index') }}">← Employees</a>
            <h1 style="margin-top:4px">Import finished</h1>
            <p><strong>{{ $created->count() }}</strong> created @if ($result['skipped']) · <strong>{{ count($result['skipped']) }}</strong> skipped @endif</p>
        </div>
        @if ($withLogin->isNotEmpty())
            <div class="actions">
                <a class="btn primary" href="{{ route('admin.staff.import.logins') }}">Download logins (CSV)</a>
            </div>
        @endif
    </div>

    @if ($withLogin->isNotEmpty())
        <div class="alert info" role="status" style="margin-bottom:16px">
            Share each password privately. Employees can change it in the app. The passwords are also on each employee's profile.
        </div>
    @endif

    @if ($created->isNotEmpty())
        <div class="card" style="margin-bottom:16px">
            <div class="card-head"><h2>Created</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Employee</th><th>Code</th><th>Login email</th><th>Password</th></tr></thead>
                    <tbody>
                    @foreach ($created as $row)
                        <tr>
                            <td><a href="{{ route('admin.staff.edit', $row['id']) }}"><strong>{{ $row['name'] }}</strong></a></td>
                            <td class="mono">{{ $row['code'] ?? '—' }}</td>
                            <td>{{ $row['email'] }}</td>
                            <td class="mono">{{ $row['password'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($result['skipped'])
        <div class="card">
            <div class="card-head"><h2>Skipped</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Line</th><th>Employee</th><th>Reason</th></tr></thead>
                    <tbody>
                    @foreach ($result['skipped'] as $row)
                        <tr><td class="muted">{{ $row['line'] }}</td><td>{{ $row['name'] ?: '—' }}</td><td class="small">{{ $row['reason'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
