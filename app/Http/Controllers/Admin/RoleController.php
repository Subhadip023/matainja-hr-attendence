<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends AdminController
{
    /** Built-in roles the app relies on; they can't be renamed. */
    public const SYSTEM_ROLES = ['Super Admin', 'HR Admin', 'Manager', 'Employee'];

    public function index(): View
    {
        $permissions = Permission::orderBy('name')->get();

        return view('admin.users.roles', [
            'roles' => Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get(),
            'permissionGroups' => $permissions->groupBy(fn ($p) => ucfirst(str_replace('_', ' ', explode('.', $p->name)[0]))),
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
        ]);
        $role = Role::create(['name' => $data['name']]);
        AuditLog::record('role.created', $role);

        return back()->with('success', "Role {$role->name} created. Tick its permissions below.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name === 'Super Admin') {
            // Super Admin always keeps every permission so nobody gets locked out.
            $role->permissions()->sync(Permission::pluck('id'));

            return back()->with('success', 'Super Admin always has every permission.');
        }

        $before = $role->permissions->pluck('name')->all();
        if (isset($data['name']) && ! in_array($role->name, self::SYSTEM_ROLES, true)) {
            $role->update(['name' => $data['name']]);
        }
        $role->permissions()->sync(Permission::whereIn('name', $data['permissions'] ?? [])->pluck('id'));
        AuditLog::record('role.updated', $role, ['permissions' => $before], ['permissions' => $data['permissions'] ?? []]);

        return back()->with('success', "Permissions for {$role->name} saved.");
    }
}
