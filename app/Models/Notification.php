<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'message',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    // Create and send notification
    public static function createAndSend($type, $title, $message, $userIds = [], $data = []): self
    {
        $notification = self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);

        // If no specific users provided, send to all admins
        if (empty($userIds)) {
            $admins = User::where('role', 'Admin')->pluck('id')->toArray();
            $userIds = $admins;
        }

        // Create user notifications
        foreach ($userIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'notification_id' => $notification->id
            ]);
        }

        return $notification;
    }

    // Scope for filtering
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}