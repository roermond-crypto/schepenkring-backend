<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycQuestionTemplate extends Model
{
    protected $table = 'kyc_question_templates';

    protected $fillable = [
        'section', 'question', 'action', 'translations', 'field_type',
        'options', 'risk_points', 'risk_flag', 'required',
        'conditional_on_question_id', 'conditional_show_when',
        'sort_order', 'active',
    ];

    protected $casts = [
        'options'       => 'array',
        'translations'  => 'array',
        'required'      => 'boolean',
        'active'        => 'boolean',
        'risk_points'   => 'integer',
    ];

    public function translate(string $locale, string $field): string
    {
        if ($locale !== 'nl') {
            $value = $this->translations[$locale][$field] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return (string) ($this->{$field} ?? '');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'conditional_on_question_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'conditional_on_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(KycAnswer::class, 'question_template_id');
    }

    public function isConditional(): bool
    {
        return $this->conditional_on_question_id !== null;
    }
}
