@extends('admin.layout')
@section('title', 'Audit log')

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit log</h1>
            <p>Who changed what, from the app or this console.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <form method="GET" class="toolbar" style="flex:1">
                <select name="action" onchange="this.form.submit()">
                    <option value="">All areas</option>
                    @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>@endforeach
                </select>
                <select name="actor" onchange="this.form.submit()">
                    <option value="">Anyone</option>
                    @foreach ($actors as $u)<option value="{{ $u->id }}" @selected(request('actor') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" title="From">
                <input type="date" name="to" value="{{ request('to') }}" title="To">
                <button class="btn" type="submit">Filter</button>
                @if (request()->hasAny(['action', 'actor', 'from', 'to']))<a class="btn ghost" href="{{ route('admin.audit.index') }}">Clear</a>@endif
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>IP</th><th>Details</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    @php
                        $before = $log->before ?? [];
                        $after = $log->after ?? [];
                        $changed = [];
                        if (is_array($before) && is_array($after) && $before && $after && ! array_is_list($after)) {
                            foreach ($after as $k => $v) {
                                if (in_array($k, ['updated_at', 'created_at'], true)) continue;
                                if (! array_key_exists($k, $before) || $before[$k] !== $v) $changed[$k] = [$before[$k] ?? null, $v];
                            }
                        }
                        $show = fn ($v) => is_scalar($v) || $v === null ? \Illuminate\Support\Str::limit((string) ($v ?? '∅'), 60) : \Illuminate\Support\Str::limit(json_encode($v), 60);
                    @endphp
                    <tr>
                        <td class="nowrap">{{ $log->created_at?->setTimezone($tz)->format('d M Y, h:i A') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}<span class="sub">{{ $log->actor?->email }}</span></td>
                        <td><span class="badge brand">{{ $log->action }}</span></td>
                        <td class="small">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="mono">{{ $log->ip_address ?? '—' }}</td>
                        <td class="small" style="max-width:420px">
                            @if ($changed)
                                @foreach (array_slice($changed, 0, 6, true) as $k => [$old, $new])
                                    <div><span class="muted">{{ $k }}:</span> {{ $show($old) }} → <strong>{{ $show($new) }}</strong></div>
                                @endforeach
                                @if (count($changed) > 6)<div class="muted">+{{ count($changed) - 6 }} more</div>@endif
                            @elseif ($before || $after)
                                <details><summary style="color:var(--brand)">View data</summary>
                                    <pre class="mono" style="white-space:pre-wrap;margin:6px 0 0">{{ json_encode(['before' => $before ?: null, 'after' => $after ?: null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No entries match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('admin.partials.pagination') }}
    </div>
@endsection
