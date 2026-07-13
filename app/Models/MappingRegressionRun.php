<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MappingRegressionRun extends Model
{
    protected $fillable = [
        'mapping_version',
        'total_yachts',
        'passed_count',
        'failed_count',
        'triggered_by_id',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(MappingRegressionRunResult::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }
}
