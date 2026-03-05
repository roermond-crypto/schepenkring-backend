<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborWidgetAiAdvice extends Model
{
    protected $table = 'harbor_widget_ai_advice';

    protected $fillable = [
        'harbor_id',
        'week_start',
        'issues',
        'suggestions',
        'priority',
        'user_message',
    ];

    protected $casts = [
        'week_start' => 'date',
        'issues' => 'array',
        'suggestions' => 'array',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
