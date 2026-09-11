<?php

namespace App\NovaAI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nova is an executive-only surface: every route in the Nova AI module -
 * the ask endpoint included - is walled off to accounts with the literal
 * 'admin' role, independent of the per-module permissions matrix that
 * governs the rest of the app. A manager or agent granted 'edit' on some
 * unrelated module must never be able to reach Nova through it.
 */
class EnsureNovaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            abort(403, 'Nova is only available to administrators.');
        }

        return $next($request);
    }
}
