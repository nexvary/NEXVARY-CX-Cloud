<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    // ── Forgot Password ───────────────────────────────────────────────────────

    public function showForgotForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always return the same response regardless of whether the email exists
        // to prevent user enumeration.
        Password::sendResetLink(
            ['email' => strtolower($request->input('email'))]
        );

        return back()->with('status', __('passwords.sent'));
    }

    // ── Reset Password ────────────────────────────────────────────────────────

    public function showResetForm(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Invalidate all existing web sessions
                $user->tokens()->where('name', 'not like', 'device-%')->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                             ->with('success', __('passwords.reset'));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
