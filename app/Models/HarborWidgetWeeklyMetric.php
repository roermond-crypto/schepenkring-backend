<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborWidgetWeeklyMetric extends Model
{
    protected $fillable = [
        'harbor_id',
        'week_start',
        'impressions',
        'visible_rate',
        'clicks',
        'ctr',
        'mobile_ctr',
        'desktop_ctr',
        'avg_scroll_before_click',
        'avg_time_before_click',
        'error_count',
        'reliability_score',
        'conversion_score',
        'computed_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'computed_at' => 'datetime',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
