<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BoatCheck extends Model
{
    protected $table = 'boat_check';

    protected $fillable = [
        'question_text',
        'type',
        'options',
        'required',
        'ai_prompt',
        'evidence_sources',
        'weight',
    ];

    protected $casts = [
        'required' => 'boolean',
        'evidence_sources' => 'array',
        'options' => 'array',
    ];

    public function boatTypes(): BelongsToMany
    {
        return $this->belongsToMany(BoatType::class, 'boat_check_boat_type');
    }
}