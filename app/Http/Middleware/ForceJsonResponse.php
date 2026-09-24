<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Every /api/* request is JSON-only. Without this, a client that omits
     * an Accept header trips Laravel's default "redirect to login" behavior
     * on 401s instead of a clean JSON error, because there is no web login
     * route in this API-only app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
