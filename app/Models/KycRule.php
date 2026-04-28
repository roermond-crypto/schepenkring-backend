<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'audience',
        'conditions_json',
        'score_delta',
        'flag_code',
        'outcome_override',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'conditions_json' => 'json',
        'is_active' => 'boolean',
    ];
}
