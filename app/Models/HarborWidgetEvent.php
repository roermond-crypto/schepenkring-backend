<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborWidgetEvent extends Model
{
    protected $fillable = [
        'harbor_id',
        'event_type',
        'placement',
        'url',
        'referrer',
        'device_type',
        'viewport_width',
        'viewport_height',
        'scroll_depth',
        'time_on_page_before_click',
        'widget_version',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
