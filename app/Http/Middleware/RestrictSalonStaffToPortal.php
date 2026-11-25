<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RestrictSalonStaffToPortal
{
    /**
     * Enforce strict server-side isolation so `salon_staff` users can ONLY access
     * the Staff Self-Service Portal (`/my-profile`), PIN-gated contract links (`/my-contract/*`),
     * and logout — blocking every operational CRM route and JSON/API endpoint with HTTP 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isSalonStaff()) {
            return $next($request);
        }

        if (!$user->is_active) {
            return EnsureActiveUser::logoutDeactivated($request);
        }

        $path = ltrim($request->path(), '/');

        // Always allow portal routes, staff contract portal links, logout, and healthcheck.
        if (
            $path === 'my-profile'
            || str_starts_with($path, 'my-profile/')
            || str_starts_with($path, 'my-contract/')
            || $path === 'logout'
            || $path === 'up'
        ) {
            return $next($request);
        }

        // Redirect root / login attempts cleanly to the Staff Self-Service Portal.
        if ($path === '' || $path === 'login') {
            $portalUrl = Route::has('portal.profile') ? route('portal.profile') : '/my-profile';

            return redirect()->to($portalUrl);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Access restricted to the Staff Self-Service Portal.',
            ], 403);
        }

        abort(403, 'Access restricted to the Staff Self-Service Portal.');
    }
}
