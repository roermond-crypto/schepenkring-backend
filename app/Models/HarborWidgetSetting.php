<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborWidgetSetting extends Model
{
    protected $fillable = [
        'harbor_id',
        'domain',
        'widget_version',
        'placement_default',
        'widget_selector',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
