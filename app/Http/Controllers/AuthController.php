<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateUser;
use App\Repositories\UserSheetRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Google OAuth login, mirroring the getCurrentUser() contract of Auth.gs:
 * only active rows of the Users sheet may sign in.
 */
class AuthController extends Controller
{
    public function __construct(private readonly UserSheetRepository $users) {}

    public function login(Request $request): View
    {
        return view('login', [
            'error' => $request->query('error'),
            'reason' => $request->query('reason'),
        ]);
    }

    public function redirect(): Response
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        error_log('[AUTH] Callback hit. Query: ' . json_encode($request->all()));

        $googleUser = null;
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            error_log('[AUTH] Socialite success. Email: ' . ($googleUser ? $googleUser->getEmail() : 'null'));
        } catch (Throwable $e) {
            error_log('[AUTH] Socialite exception: ' . $e->getMessage());
            return $this->accessDenied($request, 'OAUTH_ERROR: ' . $e->getMessage());
        }

        $email = $googleUser ? (string) $googleUser->getEmail() : '';
        if ($email === '') {
            error_log('[AUTH] Google returned empty email');
            return $this->accessDenied($request, 'EMPTY_EMAIL');
        }

        error_log('[AUTH] Searching sheet for: ' . $email);
        $user = null;
        try {
            $user = $this->users->findActiveUserByEmail($email);
            error_log('[AUTH] Sheet search result: ' . json_encode($user));
        } catch (Throwable $e) {
            error_log('[AUTH] Sheet search exception: ' . $e->getMessage());
            return $this->accessDenied($request, 'SHEET_ERROR: ' . $e->getMessage());
        }

        if ($user === null) {
            error_log('[AUTH] User not found or inactive in sheet: ' . $email);
            return $this->accessDenied($request, 'NOT_IN_SHEET: ' . $email);
        }

        $request->session()->regenerate();
        $request->session()->put(AuthenticateUser::SESSION_KEY, $user['email']);

        error_log('[AUTH] Login successful for: ' . $email);
        return redirect('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();

        return redirect('/login');
    }

    private function accessDenied(Request $request, string $reason = ''): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->session()->forget(AuthenticateUser::SESSION_KEY);
        }

        $url = '/login?error=ACCESS_DENIED';
        if ($reason !== '') {
            $url .= '&reason=' . urlencode($reason);
        }

        return redirect($url);
    }
}
