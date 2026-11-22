<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that the authenticated Sanctum token carries the 'mobile' ability.
 * Also enforces 'mobile.admin' for privileged write operations (pairing, restart).
 *
 * This prevents web session tokens or other service tokens from being used
 * to access the mobile API endpoints.
 */
class EnsureMobileToken
{
    public function handle(Request $request, Closure $next, string $ability = 'mobile'): Response
    {
        if (!$request->user()?->tokenCan($ability)) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => "Token does not have the required '{$ability}' ability.",
            ], 403);
        }

        return $next($request);
    }
}
