<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action',
            ], 403);
        }

        return $next($request);
    }
}
