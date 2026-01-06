<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user;

        if (!$user || !in_array($user->role->name, $roles)) {
            return response()->json(['error' => 'You do not have the necessary role to access this resource.'], 403);
        }

        return $next($request);
    }
}
