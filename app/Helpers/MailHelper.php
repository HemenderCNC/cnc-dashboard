<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;

class MailHelper
{
    /**
     * Send an email using the provided details.
     *
     * @param string $to Recipient's email address
     * @param string $subject Email subject
     * @param string $view Blade view for the email content
     * @param array $data Data to pass to the email view
     * @param array $attachments (Optional) Attachments for the email
     * @return bool True if the email is sent successfully, false otherwise
     */
    public static function sendMail($to, $subject, $view, $data = [], $attachments = [])
    {
        try {
            Mail::send($view, $data, function ($message) use ($to, $subject, $attachments) {
                $message->to($to)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));

                // Add attachments if provided
                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        $message->attach($attachment);
                    }
                }
            });

            return true;
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
}
