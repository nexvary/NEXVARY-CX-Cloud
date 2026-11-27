<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $credentials['email'] = strtolower($credentials['email']);
        $credentials['active'] = true;
        $credentials['deleted_at'] = null;
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $user = Auth::user();

        $request->session()->regenerate();

        if ($user->mfa_enabled) {
            $request->session()->put('mfa_pending', true);
            return redirect()->route('mfa');
        }

        $this->audit($request, 'login');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->audit($request, 'logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function audit(Request $request, string $action): void
    {
        DB::table('audit_logs')->insert([
            'id' => Str::uuid(),
            'user_id' => $request->user()->getKey(),
            'action' => $action,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
