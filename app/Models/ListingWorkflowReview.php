<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingWorkflowReview extends Model
{
    protected $fillable = [
        'listing_workflow_id',
        'actor_id',
        'actor_role',
        'action',
        'message',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ListingWorkflow::class, 'listing_workflow_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
