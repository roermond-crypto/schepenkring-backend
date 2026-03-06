<?php

namespace App\Services;

use App\Events\NotificationCountUpdated;
use App\Events\NotificationCreated;
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
        bool $allowEmail = true,
        ?int $locationId = null
    ): UserNotification {
        $notification = Notification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'location_id' => $locationId,
        ]);

        $userNotification = UserNotification::create([
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'read' => false,
            'read_at' => null,
        ]);

        $userNotification->load('notification');

        if ($allowRealtime && $user->notifications_enabled) {
            $unreadCount = $this->unreadCount($user->id);
            broadcast(new NotificationCreated($userNotification, $unreadCount, $locationId));
            broadcast(new NotificationCountUpdated($user->id, $unreadCount, $locationId));
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

        return $userNotification;
    }

    /**
     * @param iterable<User> $users
     */
    public function notifyUsers(
        iterable $users,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?Mailable $email = null,
        bool $allowRealtime = true,
        bool $allowEmail = true,
        ?int $locationId = null
    ): void {
        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $data, $email, $allowRealtime, $allowEmail, $locationId);
        }
    }

    private function unreadCount(int $userId): int
    {
        return UserNotification::where('user_id', $userId)->where('read', false)->count();
    }
}
