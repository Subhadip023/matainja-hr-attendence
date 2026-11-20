@extends('admin.layout')
@section('title', 'Users & logins')

@section('content')
    <div class="page-head">
        <div>
            <h1>Users & logins</h1>
            <p>Everyone who can sign in to the app. Change roles, reset passwords or sign people out of their phones.</p>
        </div>
        <div class="actions"><a class="btn" href="{{ route('admin.roles.index') }}">Roles & permissions</a></div>
    </div>

    <div class="pills" style="margin-bottom:12px">
        <a href="{{ route('admin.users.index', request()->except('role', 'page')) }}" class="{{ request('role') ? '' : 'active' }}">All</a>
        @foreach ($roles as $r)
            <a href="{{ route('admin.users.index', array_merge(request()->except('page'), ['role' => $r->name])) }}" class="{{ request('role') === $r->name ? 'active' : '' }}">{{ $r->name }} {{ $r->users_count }}</a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head">
            <form method="GET" class="toolbar" style="flex:1">
                @if (request('role'))<input type="hidden" name="role" value="{{ request('role') }}">@endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or email">
                <button class="btn" type="submit">Search</button>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Roles</th><th>Employee</th><th>App sessions</th><th class="right">Actions</th></tr></thead>
                <tbody>
                @forelse ($users as $u)
                    @php $self = $u->id === auth()->id(); @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                <div><strong>{{ $u->name }}</strong> @if ($self)<span class="badge info">You</span>@endif<span class="sub">{{ $u->email }}</span></div>
                            </div>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.roles', $u) }}" class="toolbar">
                                @csrf @method('PUT')
                                @foreach ($roles as $r)
                                    <label class="check small" style="font-weight:400">
                                        <input type="checkbox" name="roles[]" value="{{ $r->name }}" @checked($u->roles->contains('name', $r->name))
                                               @if ($self && $r->name === 'Super Admin') onclick="return false" @endif>
                                        {{ $r->name }}
                                    </label>
                                @endforeach
                                <button class="btn sm" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            @if ($u->staff)
                                <a href="{{ route('admin.staff.edit', $u->staff) }}" class="mono">{{ $u->staff->employee_code }}</a>
                                <span class="sub">{{ ucfirst($u->staff->employment_status) }}</span>
                            @else
                                <span class="muted small">Not linked</span>
                            @endif
                        </td>
                        <td>{{ $u->tokens_count }} device{{ $u->tokens_count === 1 ? '' : 's' }}</td>
                        <td class="right nowrap">
                            <form class="inline" method="POST" action="{{ route('admin.users.password', $u) }}"
                                  data-confirm="Generate a new password for {{ $u->email }}?{{ $self ? '' : ' They will be signed out of the app.' }}">
                                @csrf<button class="btn sm">Reset password</button>
                            </form>
                            @if ($u->tokens_count > 0)
                                <form class="inline" method="POST" action="{{ route('admin.users.revoke', $u) }}" data-confirm="Sign {{ $u->name }} out of the app on all devices?">
                                    @csrf<button class="btn sm danger">Sign out</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links('admin.partials.pagination') }}
    </div>
@endsection
