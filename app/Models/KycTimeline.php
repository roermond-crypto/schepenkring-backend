<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycTimeline extends Model
{
    protected $table = 'kyc_timeline';

    protected $fillable = [
        'kyc_case_id', 'event', 'description',
        'actor_id', 'actor_name', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function kycCase(): BelongsTo { return $this->belongsTo(KycCase::class); }
    public function actor(): BelongsTo   { return $this->belongsTo(User::class, 'actor_id'); }
}
