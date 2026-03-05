<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'contact_id',
        'email_address',
        'template_id',
        'template_version',
        'locale',
        'event_type_id',
        'subject',
        'status',
        'sent_at',
        'error_message',
        'provider_message_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];
}
