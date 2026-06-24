<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeRun extends Model
{
    protected $fillable = [
        'source',
        'status',
        'started_at',
        'finished_at',
        'pages_crawled',
        'boats_seen',
        'boats_imported',
        'boats_updated',
        'boats_skipped',
        'boats_invalid',
        'failed_pages',
        'expected_total',
        'completeness_ratio',
        'metadata',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'pages_crawled' => 'integer',
        'boats_seen' => 'integer',
        'boats_imported' => 'integer',
        'boats_updated' => 'integer',
        'boats_skipped' => 'integer',
        'boats_invalid' => 'integer',
        'failed_pages' => 'integer',
        'expected_total' => 'integer',
        'completeness_ratio' => 'float',
        'metadata' => 'array',
    ];

    public function passedCompletenessGate(float $minimum = 0.98): bool
    {
        return $this->status === 'completed'
            && (float) $this->completeness_ratio >= $minimum;
    }
}
