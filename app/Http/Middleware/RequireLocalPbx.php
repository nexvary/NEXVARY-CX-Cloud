<?php

namespace App\Http\Middleware;

use App\Services\PBX\PbxMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLocalPbx
{
    public function handle(Request $request, Closure $next): Response
    {
        PbxMode::requireLocalManagement();

        return $next($request);
    }
}
