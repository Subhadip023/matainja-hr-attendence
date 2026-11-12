<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->when($request->query('search'), fn ($q, $s) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"))
            ->paginate(20);

        return ApiResponder::success($users, 'Users retrieved');
    }

    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roleIds = Role::whereIn('name', $validated['roles'])->pluck('id');
        $before = $user->roles->pluck('name')->all();

        $user->roles()->sync(
            $roleIds->mapWithKeys(fn ($id) => [$id => ['model_type' => User::class]])->all()
        );

        AuditLog::record('user.roles_updated', $user, ['roles' => $before], ['roles' => $validated['roles']]);

        return ApiResponder::success(
            $user->fresh()->load('roles:id,name'),
            'Roles updated'
        );
    }
}
