<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\LoginSessionController;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class TrackSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Track session if the user is authenticated and attached to the request
        if (isset($request->user) && isset($request->user->id)) {
            $userId = $request->user->id;
            $cacheKey = 'last_session_track_' . $userId;

            // Only track once every 60 seconds to avoid excessive DB writes
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                try {
                    $controller = app(LoginSessionController::class);
                    
                    // Track both timesheet and login session
                    $controller->trackTimesheetSession($userId);
                    $controller->trackLoginSessions($userId);
                    
                    // Store in cache for 60 seconds
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60);
                } catch (\Exception $e) {
                    // Log error but don't interrupt the request
                    Log::error('Session tracking failed in TrackSession middleware: ' . $e->getMessage());
                }
            }
        }

        return $response;
    }
}
