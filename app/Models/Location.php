<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'status',
        'public_visible',
        'location_color',
        'hero_image',
        'description_nl',
        'description_en',
        'description_de',
        'opening_hours',
        'default_seller_id',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'chat_widget_enabled',
        'chat_widget_welcome_text',
        'chat_widget_theme',
        'bids_page_enabled',
        'seller_bid_notifications_enabled',
        'direct_buyer_seller_chat_enabled',
        'bid_routing_mode',
        'deleted_by',
        'delete_reason',
        'address_line1',
        'street_number',
        'postal_code',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'chat_widget_enabled'                => 'boolean',
        'bids_page_enabled'                  => 'boolean',
        'seller_bid_notifications_enabled'   => 'boolean',
        'direct_buyer_seller_chat_enabled'   => 'boolean',
        'public_visible'                     => 'boolean',
        'latitude'                           => 'float',
        'longitude'                          => 'float',
        'opening_hours'                      => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->userRelation();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'client_location_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->userRelation()
            ->where('users.type', UserType::EMPLOYEE->value);
    }

    public function activeEmployees(): BelongsToMany
    {
        $relation = $this->userRelation()
            ->where('users.type', UserType::EMPLOYEE->value);

        if (self::locationUserSupportsActiveFlag()) {
            $relation->wherePivot('active', true);
        }

        return $relation;
    }

    public function yachts(): HasMany
    {
        return $this->hasMany(Yacht::class);
    }

    public function knowledgeQuestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainQuestion::class);
    }

    public function knowledgeSuggestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainSuggestion::class);
    }

    public function defaultSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_seller_id');
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isPartner(): bool
    {
        return false; // Location is not a user — kept for type-safety callers
    }

    private function userRelation(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();

        if (self::locationUserSupportsActiveFlag()) {
            $relation->withPivot('active');
        }

        return $relation;
    }

    private static function locationUserSupportsActiveFlag(): bool
    {
        try {
            return Schema::hasColumn('location_user', 'active');
        } catch (\Throwable) {
            return false;
        }
    }
}
