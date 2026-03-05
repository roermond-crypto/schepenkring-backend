<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborWidgetDailySnapshot extends Model
{
    protected $fillable = [
        'harbor_id',
        'domain',
        'desktop_screenshot_path',
        'mobile_screenshot_path',
        'widget_found',
        'widget_visible',
        'widget_clickable',
        'console_errors',
        'load_time_ms',
        'checked_at',
    ];

    protected $casts = [
        'widget_found' => 'boolean',
        'widget_visible' => 'boolean',
        'widget_clickable' => 'boolean',
        'console_errors' => 'array',
        'checked_at' => 'datetime',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
