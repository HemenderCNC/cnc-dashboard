<?php

namespace App\Notifications\Channels;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\WebPushConfig;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseChannel {
    public function send($notifiable, Notification $notification) {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        $messaging = app('firebase.messaging');

        // Get FCM token
        $token = $notifiable->fcm_token;
        if (!$token) {
            Log::error('No FCM token found for user: ' . $notifiable->id);
            return;
        }

        Log::info("Sending FCM notification to: " . $token);

        try {
            $messaging->send($message);
        } catch (\Exception $e) {
            Log::error('FCM Notification Error: ' . $e->getMessage());
        }
    }
}
