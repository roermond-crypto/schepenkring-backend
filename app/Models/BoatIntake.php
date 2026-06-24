<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatIntake extends Model
{
    protected $fillable = [
        'seller_user_id',
        'seller_first_name',
        'seller_last_name',
        'seller_email',
        'seller_phone',
        'seller_address',
        'seller_postal_code',
        'seller_city',
        'seller_country',
        'seller_lat',
        'seller_lng',
        'location_id',
        'boat_brand',
        'boat_model',
        'boat_name',
        'build_year',
        'length_m',
        'width_m',
        'draft_m',
        'asking_price',
        'vat_status',
        'ce_category',
        'boat_type',
        'short_description',
        'yacht_id',
        'status',
        'intake_score',
        'score_breakdown',
        'missing_items',
        'photo_count',
        'document_count',
        'description_length',
        'resume_token',
        'resume_token_expires_at',
        'confirmation_sent',
        'confirmation_sent_at',
        'reminder_sent',
        'reminder_sent_at',
        'source_url',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'score_breakdown'          => 'array',
        'missing_items'            => 'array',
        'confirmation_sent'        => 'boolean',
        'reminder_sent'            => 'boolean',
        'resume_token_expires_at'  => 'datetime',
        'confirmation_sent_at'     => 'datetime',
        'reminder_sent_at'         => 'datetime',
        'asking_price'             => 'float',
        'intake_score'             => 'float',
        'length_m'                 => 'float',
        'width_m'                  => 'float',
        'draft_m'                  => 'float',
        'seller_lat'               => 'float',
        'seller_lng'               => 'float',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BoatIntakeFile::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BoatIntakeFile::class)->where('type', 'photo');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BoatIntakeFile::class)->where('type', '!=', 'photo');
    }

    public function sellerFullName(): string
    {
        return trim("{$this->seller_first_name} {$this->seller_last_name}");
    }
}
