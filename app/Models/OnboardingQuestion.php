<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingQuestion extends Model
{
    public const AUDIENCE_SELLER = 'seller';
    public const AUDIENCE_BUYER = 'buyer';
    public const AUDIENCE_BOTH = 'both';

    public const FIELD_TYPES = ['text', 'textarea', 'date', 'select', 'checkbox', 'radio'];

    protected $fillable = [
        'audience',
        'step_key',
        'field_type',
        'label',
        'help_text',
        'placeholder',
        'options',
        'required',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'label' => 'array',
        'help_text' => 'array',
        'placeholder' => 'array',
        'options' => 'array',
        'required' => 'boolean',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(OnboardingQuestionAnswer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->where(fn ($q) => $q->where('audience', $audience)->orWhere('audience', self::AUDIENCE_BOTH));
    }
}
