<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Restrict a route to the single super-admin account permitted to manage
     * Staff Access. Everyone else, including other admins, gets a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
