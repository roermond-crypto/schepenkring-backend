<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Mail\Mailable;

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

        return $userNotification->load('notification');
    }
}
