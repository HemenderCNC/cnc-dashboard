<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\Carbon;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next)
    {
        // Extract the token from the Authorization header
        $authHeader = $request->header('Authorization');
        
        // Check if Authorization header is present
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return response()->json(['error' => 'Authorization token not found.'], 401);
        }
        
        // Extract the token
        $token = $matches[1];

        // Find the token in the database (using hashed value if needed)
        $accessToken = PersonalAccessToken::where('token', hash('sha256', $token))->first();

        if (!$accessToken) {
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        // Check if the token is expired
        if (Carbon::parse($accessToken->expires_at)->isPast()) {
            return response()->json(['error' => 'Token has expired.'], 401);
        }

        // Retrieve the user associated with the token
        $userId = $accessToken->tokenable_id;
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        // Attach the user to the request for further use
        $request->user = $user;

        // Proceed with the request
        return $next($request);
    }
}
