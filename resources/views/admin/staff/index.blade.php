@extends('admin.layout')
@section('title', 'Employees')

@section('content')
    @php
        $statusClass = ['active' => 'ok', 'probation' => 'info', 'suspended' => 'warn', 'resigned' => '', 'terminated' => 'bad'];
    @endphp
    <div class="page-head">
        <div>
            <h1>Employees</h1>
            <p>Everyone in the organisation, with their department, manager and app access.</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('admin.staff.create') }}">+ Add employee</a>
        </div>
    </div>

    <div class="pills" style="margin-bottom:12px">
        <a href="{{ route('admin.staff.index', request()->except('status', 'page')) }}" class="{{ request('status') ? '' : 'active' }}">All {{ $counts->sum() }}</a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.staff.index', array_merge(request()->except('page'), ['status' => $st])) }}" class="{{ request('status') === $st ? 'active' : '' }}">{{ ucfirst($st) }} {{ $counts[$st] ?? 0 }}</a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head">
            <form method="GET" class="toolbar" style="flex:1">
                @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, code or phone">
                <select name="department" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
                <select name="branch" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
                </select>
                <button class="btn" type="submit">Search</button>
                @if (request()->hasAny(['q', 'department', 'branch']))<a class="btn ghost" href="{{ route('admin.staff.index', request()->only('status')) }}">Clear</a>@endif
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Employee</th><th>Code</th><th>Department</th><th>Branch</th><th>Manager</th><th>App role</th><th>Status</th><th class="right">Actions</th></tr>
                </thead>
                <tbody>
                @forelse ($staff as $s)
                    @php $name = $s->full_name ?: $s->email; @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ strtoupper(mb_substr($s->first_name ?: $name, 0, 1).mb_substr($s->last_name ?? '', 0, 1)) }}</span>
                                <div><a href="{{ route('admin.staff.edit', $s) }}"><strong>{{ $name }}</strong></a>
                                    <span class="sub">{{ $s->designation?->title ?? $s->position ?? '' }} {{ $s->email }}</span></div>
                            </div>
                        </td>
                        <td class="mono">{{ $s->employee_code }}</td>
                        <td>{{ $s->department?->name ?? '—' }}</td>
                        <td>{{ $s->branch?->name ?? '—' }}</td>
                        <td>{{ $s->manager?->full_name ?? '—' }}</td>
                        <td>
                            @if ($s->user)
                                @foreach ($s->user->roles as $r)<span class="badge brand">{{ $r->name }}</span> @endforeach
                            @else
                                <span class="muted small">No login</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $statusClass[$s->employment_status] ?? '' }}">{{ ucfirst($s->employment_status) }}</span></td>
                        <td class="right nowrap">
                            <a class="btn sm" href="{{ route('admin.staff.edit', $s) }}">Edit</a>
                            @if (in_array($s->employment_status, ['terminated', 'resigned'], true))
                                <form class="inline" method="POST" action="{{ route('admin.staff.reactivate', $s) }}">@csrf<button class="btn sm success">Reactivate</button></form>
                            @elseif ($s->user_id !== auth()->id())
                                <form class="inline" method="POST" action="{{ route('admin.staff.deactivate', $s) }}" data-confirm="Deactivate {{ $name }}? They will be signed out of the app and can no longer check in.">@csrf<button class="btn sm danger">Deactivate</button></form>
                            @endif
                            @if ($s->user_id !== auth()->id())
                                <form class="inline" method="POST" action="{{ route('admin.staff.destroy', $s) }}"
                                      data-confirm="Permanently delete {{ $name }}{{ $s->user ? ' and their app login' : '' }}? This can't be undone. Employees with attendance or other history must be force deleted from their profile.">
                                    @csrf @method('DELETE')
                                    <button class="btn sm ghost" title="Delete employee" aria-label="Delete {{ $name }}">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No employees match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $staff->links('admin.partials.pagination') }}
    </div>
@endsection
