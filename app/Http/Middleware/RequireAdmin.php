<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * requireAdmin_() from Auth.gs: after auth, non-ADMIN roles are rejected
 * with the ADMIN_REQUIRED error code.
 */
class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('currentUser');

        if (! is_array($user) || ($user['role'] ?? '') !== 'ADMIN') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'ADMIN_REQUIRED'], 403);
            }

            abort(403, 'ADMIN_REQUIRED');
        }

        return $next($request);
    }
}
