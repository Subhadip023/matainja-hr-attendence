<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return ApiResponder::error('These credentials do not match our records.', 401);
        }

        $token = $user->createToken($request->device_name ?? 'api')->plainTextToken;

        return ApiResponder::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles.permissions', 'staff')),
        ], 'Login successful');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponder::success(
            new UserResource($request->user()->load('roles.permissions', 'staff')),
            'User retrieved successfully'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponder::success(null, 'Logged out successfully');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponder::success(null, 'Logged out from all devices');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponder::error('The current password is incorrect.', 422, [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return ApiResponder::success(null, 'Password changed successfully');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return ApiResponder::success(null, 'If the account exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => $password]);
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? ApiResponder::success(null, 'Password reset successfully')
            : ApiResponder::error('Invalid or expired reset token.', 422);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $staff = $user->staff;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        if ($staff && array_key_exists('phone', $validated)) {
            $staff->update(['phone' => $validated['phone']]);
        }

        return ApiResponder::success(
            new UserResource($user->fresh()->load('roles.permissions', 'staff')),
            'Profile updated successfully'
        );
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return ApiResponder::success([
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique()
                ->values(),
        ], 'Permissions retrieved successfully');
    }
}
