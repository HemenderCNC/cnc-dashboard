<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $requiredPermission)
    {
        // Get the authenticated user from the request
        $user = $request->user;

        if (!$user || !$user->role) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // Eager load the role's permissions (and their modules) if not already loaded.
        $permissions = $user->role->permissionObjects;
        // Check if the required permission exists in the 'slug' field.
        $hasPermission = $permissions->contains(function ($permission) use ($requiredPermission) {
            return $permission->slug === $requiredPermission;
        });

        if (!$hasPermission) {
            return response()->json(['error' => 'You do not have permission to perform this action.'], 403);
        }
        return $next($request);
    }
}
