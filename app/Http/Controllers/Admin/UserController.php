<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends AdminController
{
    public function index(Request $request): View
    {
        $users = User::with(['roles:id,name', 'staff:id,user_id,employee_code,employment_status'])
            ->withCount('tokens')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->query('role'), fn ($q, $r) => $q->whereHas('roles', fn ($w) => $w->where('name', $r)))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if ($user->id === $request->user()->id && ! in_array('Super Admin', $data['roles'], true)) {
            return back()->with('error', 'You cannot remove Super Admin from your own account.');
        }

        $before = $user->roles->pluck('name')->all();
        $ids = Role::whereIn('name', $data['roles'])->pluck('id');
        $user->roles()->sync($ids->mapWithKeys(fn ($id) => [$id => ['model_type' => User::class]])->all());
        AuditLog::record('user.roles_updated', $user, ['roles' => $before], ['roles' => $data['roles']]);

        return back()->with('success', "Roles for {$user->name} updated.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string', 'min:8']]);
        $plain = $data['password'] ?? 'matainja_'.Str::random(6);

        $user->update(['password' => $plain]);
        if ($user->id !== $request->user()->id) {
            $user->tokens()->delete();
        }
        $user->staff?->update(['initial_password' => $plain]);
        AuditLog::record('user.password_reset', $user);

        return back()->with('success', "New password for {$user->email}: {$plain}");
    }

    public function revokeTokens(User $user): RedirectResponse
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();
        AuditLog::record('user.tokens_revoked', $user);

        return back()->with('success', "{$user->name} was signed out of {$count} app session(s).");
    }
}
