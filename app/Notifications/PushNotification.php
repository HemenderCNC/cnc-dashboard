<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\FirebaseChannel; // Import Custom Firebase Channel
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Illuminate\Support\Facades\Log;

class PushNotification extends Notification {
    use Queueable;

    protected $title;
    protected $body;

    public function __construct($title, $body) {
        $this->title = $title;
        $this->body = $body;
    }

    public function via($notifiable) {
        return [FirebaseChannel::class]; // Use Custom Firebase Channel
    }

    public function toFcm($notifiable) {
        // Log::info('FCM token for user: ' . $notifiable->fcm_token);

        if (!$notifiable->fcm_token) {
            // Log::error('No FCM token found for user.');
            return null; // Stop processing if no token is available
        }

        // Create Firebase Notification instance
        $firebaseNotification = FirebaseNotification::create($this->title, $this->body, null);
        $payload = CloudMessage::fromArray([
            'token' => $notifiable->fcm_token,
            'data' => [
                'title' => $this->title,
                'body' => $this->body,
                'icon' => url('/logo192.png'),
                'click_action' => url('/'),
                'extraInfo' => $this->title,
            ],
        ]);

        return $payload;
    }


    public function toArray($notifiable) {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
