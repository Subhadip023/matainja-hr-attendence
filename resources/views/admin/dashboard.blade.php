@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>{{ $today->format('l, d F Y') }} · live overview of the whole organisation</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.attendance.index') }}">Today's attendance</a>
            <a class="btn primary" href="{{ route('admin.staff.create') }}">+ Add employee</a>
        </div>
    </div>

    @php
        $s = $stats;
        $base = max(1, $s['active_staff']);
        $rate = round($s['present'] / $base * 100);
        $totalPending = array_sum($pending);
    @endphp

    <div class="stats">
        <a class="card stat" href="{{ route('admin.staff.index') }}">
            <div class="label">Active employees</div>
            <div class="value">{{ $s['active_staff'] }}</div>
            <div class="sub">{{ $s['users'] }} app logins · {{ $s['branches'] }} branch{{ $s['branches'] === 1 ? '' : 'es' }}</div>
        </a>
        <a class="card stat" href="{{ route('admin.attendance.index', ['status' => 'in']) }}">
            <div class="label">Present today</div>
            <div class="value">{{ $s['present'] }}</div>
            <div class="sub">{{ $rate }}% attendance</div>
        </a>
        <a class="card stat" href="{{ route('admin.attendance.index', ['status' => 'wfh']) }}">
            <div class="label">Working from home</div>
            <div class="value">{{ $s['wfh'] }}</div>
            <div class="sub">checked in remotely</div>
        </a>
        <a class="card stat" href="{{ route('admin.attendance.index', ['status' => 'late']) }}">
            <div class="label">Late today</div>
            <div class="value">{{ $s['late'] }}</div>
            <div class="sub">after shift grace time</div>
        </a>
        <a class="card stat" href="{{ route('admin.attendance.index', ['status' => 'leave']) }}">
            <div class="label">On leave</div>
            <div class="value">{{ $s['on_leave'] }}</div>
            <div class="sub">approved leave today</div>
        </a>
        <a class="card stat" href="{{ route('admin.approvals.index') }}">
            <div class="label">Pending approvals</div>
            <div class="value">{{ $totalPending }}</div>
            <div class="sub">{{ $pending['leave'] }} leave · {{ $pending['wfh'] }} WFH · {{ $pending['correction'] }} corrections</div>
        </a>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2>Today at a glance</h2><span class="muted small">{{ $s['active_staff'] }} people expected</span></div>
            <div class="card-body">
                @php
                    $segments = [
                        ['On time', $s['present'] - $s['late'] - $s['wfh'], 'var(--ok)'],
                        ['Late', $s['late'], 'var(--warn)'],
                        ['WFH', $s['wfh'], 'var(--wfh)'],
                        ['On leave', $s['on_leave'], 'var(--info)'],
                        ['Not in yet', $s['not_in'], '#c5ccd3'],
                    ];
                @endphp
                <div class="stackbar">
                    @foreach ($segments as [$label, $n, $color])
                        @if ($n > 0)<span style="width:{{ $n / $base * 100 }}%;background:{{ $color }}" title="{{ $label }}: {{ $n }}"></span>@endif
                    @endforeach
                </div>
                <div class="legend">
                    @foreach ($segments as [$label, $n, $color])
                        <span><span class="dot" style="background:{{ $color }}"></span>{{ $label }} <strong>{{ max(0, $n) }}</strong></span>
                    @endforeach
                </div>

                <h3 style="margin:22px 0 4px">Check-ins, last 7 days</h3>
                @php $max = max(1, $trend->max('count')); @endphp
                <div class="bars">
                    @foreach ($trend as $i => $d)
                        <div class="bar {{ $loop->last ? 'today' : '' }}" title="{{ $d['date'] }}: {{ $d['count'] }}">
                            <span class="n">{{ $d['count'] }}</span>
                            <span class="fill" style="height:{{ max(2, $d['count'] / $max * 100) }}%"></span>
                            <span class="d">{{ $d['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Needs your attention</h2><a class="small" href="{{ route('admin.approvals.index') }}">Open approvals →</a></div>
            <ul class="list">
                <li><span>Leave requests waiting</span><a class="badge {{ $pending['leave'] ? 'warn' : '' }}" href="{{ route('admin.approvals.index', ['kind' => 'leave']) }}">{{ $pending['leave'] }}</a></li>
                <li><span>Work-from-home requests waiting</span><a class="badge {{ $pending['wfh'] ? 'warn' : '' }}" href="{{ route('admin.approvals.index', ['kind' => 'wfh']) }}">{{ $pending['wfh'] }}</a></li>
                <li><span>Attendance corrections waiting</span><a class="badge {{ $pending['correction'] ? 'warn' : '' }}" href="{{ route('admin.approvals.index', ['kind' => 'correction']) }}">{{ $pending['correction'] }}</a></li>
                <li><span>Open help-desk tickets</span><span class="badge">{{ $s['open_tickets'] }}</span></li>
            </ul>
            <div class="card-head" style="border-top:1px solid var(--border)"><h2>Headcount by department</h2><a class="small" href="{{ route('admin.org.index', 'departments') }}">Manage →</a></div>
            <div class="card-body">
                @php $deptMax = max(1, $byDepartment->max('staff_count')); @endphp
                @forelse ($byDepartment as $dept)
                    <div style="margin-bottom:10px">
                        <div style="display:flex;justify-content:space-between"><span>{{ $dept->name }}</span><strong>{{ $dept->staff_count }}</strong></div>
                        <div class="meter"><span style="width:{{ $dept->staff_count / $deptMax * 100 }}%"></span></div>
                    </div>
                @empty
                    <p class="muted">No departments yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head"><h2>Recent activity</h2><a class="small" href="{{ route('admin.audit.index') }}">Full audit log →</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>When</th><th>Who</th><th>Action</th></tr></thead>
                <tbody>
                @forelse ($recentLogs as $log)
                    <tr>
                        <td class="nowrap">{{ $log->created_at?->timezone($today->timezone)->format('d M, H:i') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                        <td><span class="badge brand">{{ $log->action }}</span> @if ($log->subject_type)<span class="muted small">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No activity recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
