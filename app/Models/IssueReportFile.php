<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssueReportFile extends Model
{
    protected $fillable = [
        'issue_report_id',
        'storage_disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function report()
    {
        return $this->belongsTo(IssueReport::class, 'issue_report_id');
    }
}
