<?php

namespace App\Http\Middleware;

use App\Services\Install\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = Installation::finished();

        if (! $installed && ! $request->is('setup*')) {
            return redirect()->route('install.index');
        }

        if ($installed && $request->is('setup*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
