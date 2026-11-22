<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = file_exists(storage_path('installed')) ||
                     config('nexvary.installed', false);

        if (! $installed && ! $request->is('setup*')) {
            return redirect()->route('install.index');
        }

        if ($installed && $request->is('setup*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
