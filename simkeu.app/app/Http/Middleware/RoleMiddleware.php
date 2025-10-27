<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Pakai: 'role:admin,keuangan'  (berdasarkan name)
     * atau  : 'role:role_id,1,2'    (berdasarkan id)
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.', 'status' => false], 401);
        }

        $user = Auth::user();
        $roleUser = $user->role->name;
        foreach ($roles as $role) {
            if (strtolower($roleUser) == strtolower($role)) {
                return $next($request);
            }
        }
        return response()->json(['message' => 'Forbidden.', 'status' => false], 403);
    }
}
