<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class MfaSetupController extends Controller
{
    private Google2FA $totp;

    public function __construct()
    {
        $this->totp = new Google2FA();
    }

    // ── Enroll ────────────────────────────────────────────────────────────────

    /**
     * Show QR code enrollment page.
     * Generates a fresh secret and stores it encrypted in the session
     * (not on the user model) until confirmed.
     */
    public function showEnroll(Request $request)
    {
        $user   = $request->user();
        $secret = $request->session()->get('mfa_pending_secret')
            ?? $this->totp->generateSecretKey(32);

        $request->session()->put('mfa_pending_secret', $secret);

        $qrUrl = $this->totp->getQRCodeUrl(
            company: config('app.name', 'NEXVARY CX'),
            holder:  $user->email,
            secret:  $secret,
        );

        return Inertia::render('Settings/MfaSetup', [
            'qr_url'    => $qrUrl,
            'secret'    => $secret,       // shown as manual entry fallback
            'enrolled'  => $user->mfa_enabled,
        ]);
    }

    /**
     * Confirm the TOTP code and activate MFA.
     * Generates recovery codes and encrypts the secret on the user model.
     */
    public function enable(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $secret = $request->session()->get('mfa_pending_secret');

        if (! $secret || ! $this->totp->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.mfa_invalid')]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $request->user()->update([
            'mfa_enabled'        => true,
            'mfa_secret'         => Crypt::encryptString($secret),
            'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $recoveryCodes),
            'mfa_enrolled_at'    => now(),
        ]);

        $request->session()->forget('mfa_pending_secret');

        // Show recovery codes exactly once; store them in session for the response page
        $request->session()->put('mfa_new_recovery_codes', $recoveryCodes);

        return redirect()->route('settings.mfa.recovery');
    }

    // ── Recovery codes ────────────────────────────────────────────────────────

    public function showRecoveryCodes(Request $request)
    {
        $codes = $request->session()->pull('mfa_new_recovery_codes', []);

        return Inertia::render('Settings/MfaRecoveryCodes', [
            'codes'      => $codes,
            'just_set'   => count($codes) > 0,
        ]);
    }

    public function regenerateCodes(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['password' => ['required']]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => __('auth.password')]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $recoveryCodes),
        ]);

        $request->session()->put('mfa_new_recovery_codes', $recoveryCodes);

        return redirect()->route('settings.mfa.recovery');
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    /**
     * Disable MFA. Requires current password AND a valid TOTP code
     * to prevent session hijacking from disabling MFA silently.
     */
    public function disable(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'password' => ['required'],
            'code'     => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => __('auth.password')]);
        }

        $secret = Crypt::decryptString($user->mfa_secret);

        if (! $this->totp->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.mfa_invalid')]);
        }

        $user->update([
            'mfa_enabled'        => false,
            'mfa_secret'         => null,
            'mfa_recovery_codes' => null,
            'mfa_enrolled_at'    => null,
        ]);

        return redirect()->route('settings.mfa.enroll')
                         ->with('success', __('auth.mfa_disabled'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateRecoveryCodes(int $count = 10): array
    {
        return array_map(
            fn () => strtoupper(
                implode('-', str_split(Str::random(12), 4))
            ),
            array_fill(0, $count, null)
        );
    }
}
