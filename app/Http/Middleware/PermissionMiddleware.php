<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => "Missing permission: {$permission}",
            ], 403);
        }

        return $next($request);
    }
}
