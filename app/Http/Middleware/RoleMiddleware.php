<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Contoh penggunaan di route:
     * - role:superadmin
     * - role:dosen
     * - role:superadmin,dosen
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Kalau tidak ada role yang dikonfigurasi, langsung lolos saja
        if (empty($roles)) {
            return $next($request);
        }

        // Cek apakah role user ada di daftar roles yg diizinkan
        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden: role mismatch.',
                'required_roles' => $roles,
                'current_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
