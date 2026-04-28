<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'kyc_question_id',
        'value',
        'label',
        'sort_order',
        'score_delta',
        'flag_code',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'json',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(KycQuestion::class, 'kyc_question_id');
    }
}
