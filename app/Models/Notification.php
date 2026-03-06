<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'location_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public static function createAndSend(
        string $type,
        string $title,
        string $message,
        array $userIds = [],
        array $data = [],
        ?int $locationId = null
    ): self {
        $notification = self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'location_id' => $locationId,
        ]);

        if (empty($userIds)) {
            $userIds = User::where('type', 'ADMIN')->where('status', 'ACTIVE')->pluck('id')->all();
        }

        foreach ($userIds as $userId) {
            UserNotification::create([
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'read' => false,
                'read_at' => null,
            ]);
        }

        return $notification;
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
