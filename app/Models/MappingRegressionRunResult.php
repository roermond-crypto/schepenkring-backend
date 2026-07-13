<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingRegressionRunResult extends Model
{
    protected $fillable = [
        'mapping_regression_run_id',
        'yacht_id',
        'passed',
        'errors',
        'warnings',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'errors' => 'array',
        'warnings' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(MappingRegressionRun::class, 'mapping_regression_run_id');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
