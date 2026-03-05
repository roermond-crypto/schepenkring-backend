<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatchService
{
    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?Mailable $email = null,
        bool $allowRealtime = true,
        bool $allowEmail = true
    ): UserNotification {
        $notification = Notification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        $userNotification = UserNotification::create([
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'read' => false,
            'read_at' => null,
        ]);

        if ($allowRealtime && $user->notifications_enabled) {
            $pushService = new PushNotificationService();
            $pushService->sendToUser($user->id, $notification->title, $notification->message);
        }

        if ($email && $allowEmail && $user->email_notifications_enabled) {
            try {
                Mail::to($user->email)->send($email);
            } catch (\Throwable $e) {
                Log::error('Notification email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $userNotification->load('notification');
    }
}
