<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $locale = $request->user()?->locale ?? config('app.locale', 'en');
        app()->setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : 'en');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(
                    'id', 'name', 'email', 'locale', 'timezone', 'mfa_enabled'
                ),
                'roles' => $request->user()?->getRoleNames(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'locale' => app()->getLocale(),
        ];
    }
}
