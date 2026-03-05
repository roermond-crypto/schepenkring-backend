<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSendLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'harbor_id',
        'template_name',
        'language',
        'params',
        'reason',
        'status',
    ];

    protected $casts = [
        'params' => 'array',
    ];
}
