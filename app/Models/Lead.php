<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'client_id',
        'conversation_id',
        'assigned_employee_id',
        'status',
        'source',
        'source_url',
        'notes',
        'name',
        'email',
        'phone',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    // Retaining client method to avoid breaking existing code just in case
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
