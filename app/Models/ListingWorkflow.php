<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingWorkflow extends Model
{
    protected $fillable = [
        'boat_intake_id',
        'user_id',
        'yacht_id',
        'assigned_admin_id',
        'status',
        'seller_verification_required',
        'seller_verification_expires_at',
        'paid_at',
        'ai_generated_at',
        'admin_reviewed_at',
        'client_approved_at',
        'ready_to_publish_at',
        'published_at',
        'rejected_at',
        'archived_at',
        'last_review_message',
    ];

    protected $casts = [
        'seller_verification_required' => 'boolean',
        'seller_verification_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'ai_generated_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'ready_to_publish_at' => 'datetime',
        'published_at' => 'datetime',
        'rejected_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(BoatIntake::class, 'boat_intake_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ListingWorkflowVersion::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ListingWorkflowReview::class);
    }
}
