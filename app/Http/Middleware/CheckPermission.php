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
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Get the authenticated user from the request
        $user = $request->user;

        if (!$user || !$user->role) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // Eager load the role's permissions (and their modules) if not already loaded.
        $userPermissions = $user->role->permissionObjects;
        
        // Check if any of the required permissions exist in the 'slug' field.
        $hasPermission = $userPermissions->contains(function ($permission) use ($permissions) {
            return in_array($permission->slug, $permissions);
        });

        if (!$hasPermission) {
            return response()->json(['error' => 'You do not have permission to perform this action.'], 403);
        }
       
        return $next($request);
    }
}
