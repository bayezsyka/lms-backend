<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== $role) {
            return response()->json([
                'message' => 'Forbidden: role mismatch.',
                'required_role' => $role,
                'current_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
