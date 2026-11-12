<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponder::success(
            Role::with('permissions:id,name')->get(),
            'Roles retrieved'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->permissions()->sync(
            Permission::whereIn('name', $validated['permissions'] ?? [])->pluck('id')
        );

        AuditLog::record('role.created', $role);

        return ApiResponder::success($role->load('permissions:id,name'), 'Role created', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $role->permissions->pluck('name')->all();

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }
        if (array_key_exists('permissions', $validated)) {
            $role->permissions()->sync(
                Permission::whereIn('name', $validated['permissions'] ?? [])->pluck('id')
            );
        }

        AuditLog::record('role.updated', $role, ['permissions' => $before], ['permissions' => $validated['permissions'] ?? null]);

        return ApiResponder::success($role->fresh()->load('permissions:id,name'), 'Role updated');
    }
}
