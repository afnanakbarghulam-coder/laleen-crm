<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Ensure authenticated web users remain active on every request.
     * Immediately logs out and invalidates the session if an account has been deactivated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            return self::logoutDeactivated($request);
        }

        return $next($request);
    }

    public static function logoutDeactivated(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/login')
            ->withErrors(['email' => 'Your account has been deactivated.'])
            ->with('error', 'Your account has been deactivated.');
    }
}
