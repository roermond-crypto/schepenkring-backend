<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingWorkflowVersion extends Model
{
    protected $fillable = [
        'listing_workflow_id',
        'yacht_id',
        'version_type',
        'created_by',
        'created_by_role',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ListingWorkflow::class, 'listing_workflow_id');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
