<?php

namespace App\Http\Middleware;

use App\Services\Install\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Installation::finished(), 404);

        if (!$request->isMethod('GET')) {
            abort_unless(Installation::secureTransport($request), 403);
            $secret = (string) config('nexvary.install_secret');
            abort_unless(strlen($secret) >= 32 && hash_equals($secret, (string) $request->header('X-Setup-Token')), 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
