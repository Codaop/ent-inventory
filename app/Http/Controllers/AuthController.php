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
        return view('login', ['error' => $request->query('error')]);
    }

    public function redirect(): Response
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->accessDenied($request);
        }

        $email = (string) $googleUser->getEmail();
        $user = $email !== '' ? $this->users->findActiveUserByEmail($email) : null;

        if ($user === null) {
            return $this->accessDenied($request);
        }

        $request->session()->regenerate();
        $request->session()->put(AuthenticateUser::SESSION_KEY, $user['email']);

        return redirect('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();

        return redirect('/login');
    }

    private function accessDenied(Request $request): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->session()->forget(AuthenticateUser::SESSION_KEY);
        }

        return redirect('/login?error=ACCESS_DENIED');
    }
}
