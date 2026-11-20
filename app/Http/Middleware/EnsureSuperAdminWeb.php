<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the web admin panel: only signed-in Super Admins get through.
 * Anyone else is signed out and sent back to the panel login.
 */
class EnsureSuperAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Super Admin')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Only Super Admin accounts can open the admin panel.']);
        }

        return $next($request);
    }
}
