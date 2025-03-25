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

        if (!$user || !$request->user->role->permissions) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // Get the user's permissions
        // $permissions = collect($request->user->role->permissions ?? []);

        // Check if the required permission exists
        // $hasPermission = $permissions->contains(function ($permissionId) use ($requiredPermission) {
        //     return $permissionId === $requiredPermission;
        // });


        // Get the user's permissions from role_with_permissions
        $permissions = collect($user->role_with_permissions['permissions']);

        // Check if the required permission exists in the permission_slug field
        $hasPermission = $permissions->contains(function ($permission) use ($requiredPermission) {
            return isset($permission['permission_slug']) && $permission['permission_slug'] === $requiredPermission;
        });


        if (!$hasPermission) {
            return response()->json(['error' => 'You do not have permission to perform this action.'], 403);
        }

        return $next($request);
    }
}
