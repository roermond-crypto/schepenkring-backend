<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'severity',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──────────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Create a notification for a user.
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
        string $severity = 'info',
    ): static {
        return static::create([
            'user_id'  => $userId,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'data'     => $data,
            'severity' => $severity,
        ]);
    }
}
