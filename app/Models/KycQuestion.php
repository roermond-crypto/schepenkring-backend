<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'prompt',
        'input_type',
        'audience',
        'seller_type_scope',
        'required',
        'sort_order',
        'conditions_json',
        'is_active',
    ];

    protected $casts = [
        'conditions_json' => 'json',
        'required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(KycQuestionOption::class);
    }
}
