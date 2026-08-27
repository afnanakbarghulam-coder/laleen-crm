<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    /**
     * Restrict a route to a module permission level, e.g. `module:leads` (view,
     * the default) or `module:leads,edit`. Admins always pass (see
     * User::permissionLevel()). Anyone without the required level gets a 403.
     */
    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();

        $allowed = $user && ($level === 'edit' ? $user->canEdit($module) : $user->canView($module));

        if (!$allowed) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
