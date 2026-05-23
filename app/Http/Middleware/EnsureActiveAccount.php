<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $freshUser = $user?->fresh();

        if ($freshUser && $freshUser->AccountStatus !== 'active') {
            $user->currentAccessToken()?->delete();

            return new JsonResponse(['message' => 'Account is inactive.'], 401);
        }

        return $next($request);
    }
}
