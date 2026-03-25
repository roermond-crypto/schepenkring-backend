<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\BoatTaskTemplateRenderer;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'assignment_status',
        'assigned_to',
        'user_id',
        'created_by',
        'yacht_id',
        'appointment_id',
        'due_date',
        'reminder_at',
        'reminder_sent_at',
        'type',
        'column_id',
        'position',
        'location_id',
        'client_visible',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'client_visible' => 'boolean',
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
        return $this->belongsTo(Yacht::class, 'yacht_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function automation(): HasOne
    {
        return $this->hasOne(TaskAutomation::class, 'created_task_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class);
    }

    public function getTitleAttribute($value): string
    {
        if (! is_string($value) || $value === '') {
            return (string) $value;
        }

        if (
            ! str_contains($value, '#{boat_id}')
            && ! str_contains($value, '{boat_name}')
            && ! str_contains($value, '{{boat_name}}')
            && ! str_contains($value, '{boat_id}')
            && ! str_contains($value, '{{boat_id}}')
            && ! str_contains($value, '{yacht_name}')
            && ! str_contains($value, '{{yacht_name}}')
        ) {
            return $value;
        }

        $yacht = $this->resolveYachtForTemplateRendering();
        if (! $yacht) {
            return $value;
        }

        $recipient = $this->relationLoaded('user') ? $this->user : null;
        $rendered = app(BoatTaskTemplateRenderer::class)->render($value, $yacht, $recipient);

        return is_string($rendered) ? $rendered : $value;
    }

    private function resolveYachtForTemplateRendering(): ?Yacht
    {
        if ($this->relationLoaded('yacht') && $this->yacht) {
            return $this->yacht;
        }

        if ($this->yacht_id) {
            return $this->yacht()->first();
        }

        if ($this->relationLoaded('automation') && $this->automation) {
            if (
                $this->automation->related_type === Yacht::class
                && $this->automation->related_id
            ) {
                if ($this->automation->relationLoaded('relatedYacht') && $this->automation->relatedYacht) {
                    return $this->automation->relatedYacht;
                }

                return Yacht::query()->find($this->automation->related_id);
            }
        }

        return null;
    }
}
