<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MfaController extends Controller
{
    public function show(): \Inertia\Response
    {
        abort_unless(session('mfa_pending'), 404);

        return Inertia::render('Auth/Mfa');
    }

    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->session()->get('mfa_pending'), 404);
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = Auth::user();

        // Google Authenticator TOTP verification
        $valid = filled($user->mfa_secret) && (new \PragmaRX\Google2FA\Google2FA)->verifyKey(
            $user->mfa_secret,
            $request->code,
        );

        if (! $valid) {
            return back()->withErrors(['code' => __('auth.mfa_invalid')]);
        }

        $request->session()->forget('mfa_pending');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
