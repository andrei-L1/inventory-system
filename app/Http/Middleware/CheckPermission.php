<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request. Ensure operator has specific capabilities.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated terminal access.');
        }

        // 1. SysAdmin Absolute Bypass
        if ($user->role && $user->role->name === 'admin') {
            return $next($request);
        }

        // 2. Strict Role Verification
        if (!$user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized operation. Clearance denied.'], 403);
            }
            abort(403, 'Unauthorized operation. Clearance denied.');
        }

        return $next($request);
    }
}
