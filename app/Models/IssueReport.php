<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssueReport extends Model
{
    protected $fillable = [
        'platform_error_id',
        'user_id',
        'conversation_id',
        'message_id',
        'email',
        'subject',
        'description',
        'page_url',
        'error_reference',
        'source',
        'status',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function platformError()
    {
        return $this->belongsTo(PlatformError::class, 'platform_error_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function files()
    {
        return $this->hasMany(IssueReportFile::class, 'issue_report_id');
    }
}
