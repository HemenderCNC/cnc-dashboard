<?php
namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Illuminate\Support\Facades\Log;

class FirebaseChannel {
    public function send($notifiable, Notification $notification) {
        if (!$notifiable->fcm_token) {
            Log::error('No FCM token for user: ' . $notifiable->id);
            return;
        }

        // Check if the notification has a `toFcm` method
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        // Call `toFcm()` from the notification class
        $message = $notification->toFcm($notifiable);

        // Send the notification via Firebase
        try {
            $messaging = app('firebase.messaging');
            $messaging->sendMulticast($message, [$notifiable->fcm_token]);
            Log::info('FCM notification sent successfully!');
        } catch (\Exception $e) {
            Log::error('FCM Push Notification Error: ' . $e->getMessage());
        }
    }
}
