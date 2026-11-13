<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    /**
     * Restrict a route to a module permission level, e.g. `module:leads` (view,
     * the default), `module:leads,edit`, or pipe-separated OR modules such as
     * `module:bookings|calendar,edit`. Admins always pass (see
     * User::permissionLevel()). Anyone without the required level gets a 403.
     */
    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            return EnsureActiveUser::logoutDeactivated($request);
        }

        $modules = array_values(array_filter(array_map('trim', explode('|', $module))));
        $allowed = false;

        if ($user) {
            foreach ($modules as $mod) {
                if ($level === 'edit' ? $user->canEdit($mod) : $user->canView($mod)) {
                    $allowed = true;
                    break;
                }
            }
        }

        if (!$allowed) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
