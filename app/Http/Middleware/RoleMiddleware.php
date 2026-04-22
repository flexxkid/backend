<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $roleName = $user?->role?->RoleName;

        if (! $user || ! $roleName) {
            return new JsonResponse(['message' => 'Unauthorised'], 403);
        }

        if (! in_array($roleName, $roles, true)) {
            return new JsonResponse(['message' => 'Forbidden: insufficient role'], 403);
        }

        return $next($request);
    }
}
