<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        if ($user && $this->shouldAudit($request, $response)) {
            [$entityType, $entityId] = $this->findEntity($request);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $request->method().' '.$request->route()->uri(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'new_values' => Arr::except($request->all(), ['password', 'password_confirmation']),
                'reason' => $request->input('reason') ?: $request->input('notes'),
                'ip_address' => $request->ip(),
                'response_status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() < 400;
    }

    private function findEntity(Request $request): array
    {
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                return [class_basename($parameter), $parameter->getKey()];
            }
        }

        return [null, null];
    }
}
