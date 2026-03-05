<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',               // Low, Medium, High, Urgent, Critical
        'status',                  // To Do, In Progress, Done
        'assignment_status',       // pending, accepted, rejected (only for assigned tasks)
        'assigned_to',
        'user_id',                 // creator / owner (for personal tasks)
        'created_by',
        'yacht_id',
        'appointment_id',
        'due_date',
        'reminder_at',
        'reminder_sent_at',
        'type',                    // personal, assigned
        'column_id',
        'position',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(TaskActivityLog::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_to', $userId)
              ->orWhere('user_id', $userId)
              ->orWhere('created_by', $userId);
        });
    }
}
