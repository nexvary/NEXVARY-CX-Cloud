<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
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
        $request->validate(['code' => ['required', 'string', 'max:30']]);

        $user  = Auth::user();
        $input = trim($request->input('code'));
        $valid = false;

        if (filled($user->mfa_secret)) {
            $secret = Crypt::decryptString($user->mfa_secret);

            // Try TOTP (6-digit code)
            if (preg_match('/^\d{6}$/', $input)) {
                $valid = (new \PragmaRX\Google2FA\Google2FA)->verifyKey($secret, $input);
            }

            // Try recovery code (format XXXX-XXXX-XXXX) — single-use
            if (! $valid && is_array($user->mfa_recovery_codes)) {
                foreach ($user->mfa_recovery_codes as $index => $hashed) {
                    if (Hash::check(strtoupper($input), $hashed)) {
                        // Consume the code immediately
                        $remaining = $user->mfa_recovery_codes;
                        unset($remaining[$index]);
                        $user->update(['mfa_recovery_codes' => array_values($remaining)]);
                        $valid = true;
                        break;
                    }
                }
            }
        }

        if (! $valid) {
            return back()->withErrors(['code' => __('auth.mfa_invalid')]);
        }

        $request->session()->forget('mfa_pending');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
