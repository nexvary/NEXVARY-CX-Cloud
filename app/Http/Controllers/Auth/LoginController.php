<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $user = Auth::user();

        if ($user->mfa_enabled) {
            $request->session()->put('mfa_pending', true);
            return redirect()->route('mfa');
        }

        $request->session()->regenerate();
        activity()->causedBy($user)->log('login');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        activity()->causedBy(Auth::user())->log('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
