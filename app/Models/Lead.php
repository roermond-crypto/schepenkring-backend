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
        'converted_client_id',
        'conversation_id',
        'assigned_employee_id',
        'status',
        'source',
        'source_url',
        'notes',
        'name',
        'email',
        'phone',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'gmaps_place_id',
        'formatted_address',
        'address_components',
        'lat',
        'lng',
        'confidence',
        'maps_url',
        'geocode_query_hash',
        'last_geocode_at',
    ];

    protected $casts = [
        'address_components' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'last_geocode_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_client_id');
    }

    // Retaining client method to avoid breaking existing code just in case
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
