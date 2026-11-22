<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('mfa_pending')) {
            return redirect()->route('mfa');
        }

        return $next($request);
    }
}
