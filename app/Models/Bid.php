<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemLogs;

class Bid extends Model
{
    use HasSystemLogs;
    
    // Define which events to log
    protected $logEvents = ['created', 'updated'];
    
    protected $fillable = [
        'yacht_id',
        'user_id',
        'amount',
        'status',
        'finalized_at',
        'finalized_by'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.v\\Z');
    }
    
    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
