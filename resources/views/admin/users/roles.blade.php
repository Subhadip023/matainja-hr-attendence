@extends('admin.layout')
@section('title', 'Roles & permissions')

@section('content')
    <div class="page-head">
        <div>
            <h1>Roles & permissions</h1>
            <p>What each role may do in the app and API. Super Admin always has every permission.</p>
        </div>
        <form method="POST" action="{{ route('admin.roles.store') }}" class="toolbar">
            @csrf
            <input type="text" name="name" placeholder="New role name" required maxlength="100" style="width:200px">
            <button class="btn primary" type="submit">+ Add role</button>
        </form>
    </div>

    <div class="stack">
        @foreach ($roles as $role)
            @php
                $isSuper = $role->name === 'Super Admin';
                $has = $role->permissions->pluck('name')->all();
            @endphp
            <details class="card" @if ($loop->first || request('open') == $role->id) open @endif>
                <summary class="card-head">
                    <div>
                        <h2 style="display:inline">{{ $role->name }}</h2>
                        @if (in_array($role->name, $systemRoles, true))<span class="badge">Built-in</span>@endif
                    </div>
                    <span class="muted small">{{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }} · {{ $isSuper ? 'all' : count($has) }} permissions ▾</span>
                </summary>
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="card-body">
                    @csrf @method('PUT')
                    @unless (in_array($role->name, $systemRoles, true))
                        <div class="field" style="max-width:320px">
                            <label>Role name</label>
                            <input type="text" name="name" value="{{ $role->name }}" maxlength="100">
                        </div>
                    @endunless
                    <fieldset @disabled($isSuper)>
                        <div class="perm-grid">
                            @foreach ($permissionGroups as $group => $perms)
                                <div class="perm-group">
                                    <h3>{{ $group }}</h3>
                                    @foreach ($perms as $p)
                                        <label class="check">
                                            <input type="checkbox" name="permissions[]" value="{{ $p->name }}" @checked($isSuper || in_array($p->name, $has, true))>
                                            <span class="small">{{ str_replace('_', ' ', \Illuminate\Support\Str::after($p->name, '.')) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    @unless ($isSuper)
                        <div class="actions" style="justify-content:flex-end;margin-top:14px">
                            <button class="btn primary" type="submit">Save {{ $role->name }}</button>
                        </div>
                    @endunless
                </form>
            </details>
        @endforeach
    </div>
@endsection
