<?php

namespace App\Http\Middleware;

use App\Repositories\UserSheetRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current user from the Users sheet on every request, exactly
 * like getCurrentUser() did in Auth.gs: the login session only carries the
 * Google email; role and active state are always read live from the sheet.
 */
class AuthenticateUser
{
    public const SESSION_KEY = 'auth_email';

    public function __construct(private readonly UserSheetRepository $users) {}

    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        $user = is_string($email) && $email !== ''
            ? $this->users->findActiveUserByEmail($email)
            : null;

        if ($user === null) {
            if ($request->hasSession()) {
                $request->session()->forget(self::SESSION_KEY);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => 'ACCESS_DENIED'], 401);
            }

            return redirect('/login');
        }

        $request->attributes->set('currentUser', $user);

        return $next($request);
    }
}
