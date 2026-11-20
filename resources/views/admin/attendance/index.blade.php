@extends('admin.layout')
@section('title', 'Attendance')

@section('content')
    @php
        $badge = ['in' => 'ok', 'late' => 'warn', 'wfh' => 'wfh', 'leave' => 'info', 'absent' => ''];
        $isToday = $date->isSameDay($today);
        $q = request()->except('page');
        $fmtTime = fn ($t) => $t ? $t->copy()->setTimezone($tz)->format('h:i A') : null;
        $place = function (?array $loc, bool $wfh) {
            if (! $loc) {
                return null;
            }
            $dist = $loc['distance_from_office_meters'];
            $text = $wfh ? 'Remote' : ($dist === null ? 'Location' : ($dist < 1000 ? $dist.' m from office' : round($dist / 1000, 1).' km from office'));

            return ['text' => $text, 'url' => 'https://www.google.com/maps?q='.$loc['latitude'].','.$loc['longitude'], 'far' => ! $wfh && $dist !== null && $dist > 300];
        };
    @endphp

    <div class="page-head">
        <div>
            <h1>Attendance</h1>
            <p>{{ $date->format('l, d F Y') }}{{ $isToday ? ' · today' : '' }} · {{ $total }} employee{{ $total === 1 ? '' : 's' }} expected</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.attendance.index', array_merge($q, ['date' => $date->subDay()->toDateString()])) }}">‹ Prev day</a>
            @unless ($isToday)<a class="btn" href="{{ route('admin.attendance.index', array_merge($q, ['date' => null])) }}">Today</a>@endunless
            @if ($date->lt($today))<a class="btn" href="{{ route('admin.attendance.index', array_merge($q, ['date' => $date->addDay()->toDateString()])) }}">Next day ›</a>@endif
            <a class="btn primary" href="{{ route('admin.attendance.export', array_merge($q, ['date' => $date->toDateString()])) }}">Export CSV</a>
        </div>
    </div>

    @if ($holiday)
        <div class="alert info"><strong>Holiday:</strong>&nbsp;{{ $holiday->name }}</div>
    @elseif ($date->isWeekend())
        <div class="alert info">Weekend: attendance is optional.</div>
    @endif
    @if ($isWfhDay)
        <div class="alert wfh"><strong>Work-from-home day.</strong>&nbsp;Everyone can check in from anywhere; the office geofence is skipped and the punch location is still recorded.</div>
    @endif

    <div class="stats">
        @foreach ($filters as $key => $label)
            <a class="card stat {{ request('status') === $key ? 'active' : '' }}"
               href="{{ route('admin.attendance.index', array_merge($q, ['status' => request('status') === $key ? null : $key])) }}">
                <div class="label"><span class="dot" style="background:var(--{{ ['in' => 'ok', 'late' => 'warn', 'wfh' => 'wfh', 'leave' => 'info', 'absent' => 'muted'][$key] }})"></span>{{ $key === 'absent' && ! $isToday ? 'Absent' : $label }}</div>
                <div class="value">{{ $counts[$key] }}</div>
                <div class="sub">{{ $total ? round($counts[$key] / $total * 100) : 0 }}%</div>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head">
            <form method="GET" class="toolbar" style="flex:1">
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}" onchange="this.form.submit()">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search employee or code">
                <select name="branch" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
                </select>
                <select name="department" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
                @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                <button class="btn" type="submit">Apply</button>
                @if (request()->hasAny(['q', 'branch', 'department', 'status']))<a class="btn ghost" href="{{ route('admin.attendance.index', ['date' => $date->toDateString()]) }}">Clear</a>@endif
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Employee</th><th>Shift</th><th>Check-in</th><th>Check-out</th><th>Worked</th><th>Check-in place</th><th>Check-out place</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    @php
                        $s = $r['staff'];
                        $a = $r['attendance'];
                        $wfh = $r['bucket'] === 'wfh';
                        $in = $place($r['in_location'], $wfh);
                        $out = $place($r['out_location'], $wfh);
                        $worked = $a?->worked_minutes;
                        if ($a && $a->check_in_at && ! $a->check_out_at && $isToday) {
                            $worked = (int) $a->check_in_at->diffInMinutes(now());
                        }
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ strtoupper(mb_substr($s->first_name ?: $s->full_name, 0, 1).mb_substr($s->last_name ?? '', 0, 1)) }}</span>
                                <div><a href="{{ route('admin.staff.edit', $s) }}"><strong>{{ $s->full_name }}</strong></a>
                                    <span class="sub">{{ $s->employee_code }} · {{ $s->department?->name ?? 'No department' }}</span></div>
                            </div>
                        </td>
                        <td class="nowrap">{{ $a?->shift ? substr($a->shift->start_time, 0, 5).'–'.substr($a->shift->end_time, 0, 5) : '—' }}</td>
                        <td class="nowrap">
                            @if ($a?->check_in_at)
                                <strong>{{ $fmtTime($a->check_in_at) }}</strong>
                                <span class="sub">{{ $a->late_minutes > 0 ? $a->late_minutes.' min late' : ($wfh ? 'remote' : 'on time') }}</span>
                            @else — @endif
                        </td>
                        <td class="nowrap">
                            @if ($a?->check_out_at)
                                <strong>{{ $fmtTime($a->check_out_at) }}</strong>
                                @if ($a->overtime_minutes > 0)<span class="sub">+{{ $a->overtime_minutes }} min overtime</span>@endif
                            @elseif ($a?->check_in_at)
                                <span class="badge {{ $isToday ? 'ok' : 'warn' }}">{{ $isToday ? 'Working' : 'Missed check-out' }}</span>
                            @else — @endif
                        </td>
                        <td class="nowrap" style="min-width:110px">
                            @if ($worked)
                                {{ intdiv($worked, 60) }}h {{ str_pad($worked % 60, 2, '0', STR_PAD_LEFT) }}m
                                <div class="meter"><span style="width:{{ min(100, $worked / 540 * 100) }}%"></span></div>
                            @else — @endif
                        </td>
                        @foreach ([$in, $out] as $p)
                            <td class="nowrap">
                                @if ($p)
                                    <a href="{{ $p['url'] }}" target="_blank" rel="noopener" title="Open in Google Maps">📍 {{ $p['text'] }}</a>
                                    @if ($p['far'])<span class="sub" style="color:var(--warn)">outside usual range</span>@endif
                                @elseif ($a?->check_in_at && $loop->first)
                                    <span class="muted small">Not recorded</span>
                                @else — @endif
                            </td>
                        @endforeach
                        <td><span class="badge {{ $badge[$r['bucket']] }}">{{ $r['label'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No employees match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
