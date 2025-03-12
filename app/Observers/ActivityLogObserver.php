<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\Carbon;

class ActivityLogObserver
{
    protected function getUserData($token){
        $userData = [];
        if (!$token || !preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            return $userData;
        }
        
        // Extract the token
        $token = $matches[1];

        // Find the token in the database (using hashed value if needed)
        $accessToken = PersonalAccessToken::where('token', hash('sha256', $token))->first();

        if (!$accessToken) {
            return $userData;
        }

        // Check if the token is expired
        if (Carbon::parse($accessToken->expires_at)->isPast()) {
            return $userData;
        }

        // Retrieve the user associated with the token
        $userId = $accessToken->tokenable_id;
        $user = User::find($userId);
        if (!$user) {
            return $userData;
        }
        // Attach the user to the request for further use
        $userData = $user;
        return $userData;
    }
    
    protected function logActivity($model, $action)
    {
        $token = request()->header('Authorization'); // Get token from request headers
        $userData = $this->getUserData($token);

        // Get user ID (modify based on your authentication system)
        // $user = auth()->user();
        $user = $userData;
        $userId = $user ? $user->_id : null;
        if($userData){
            // Prepare log data
            $logData = [
                'user'       => [
                    '_id'        => $userId,
                    'first_name'    => $user->name,
                    'last_name'    => $user->last_name,
                    'email'    => $user->email,
                ],
                'action'     => $action,
                'model'      => get_class($model),
                'model_id'   => $model->_id,
                'old_values' => $action === 'created' ? null : $model->getOriginal(),
                'new_values' => $action === 'deleted' ? null : $model->getAttributes(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
            ];
        }else{
            return;
        }


        // Prevent logging itself to avoid infinite loops
        if ($model instanceof ActivityLog) {
            return;
        }

        // Save log
        ActivityLog::create($logData);
    }

    public function created($model)
    {
        $this->logActivity($model, 'created');
    }

    public function updated($model)
    {
        $this->logActivity($model, 'updated');
    }

    public function deleted($model)
    {
        $this->logActivity($model, 'deleted');
    }
}
