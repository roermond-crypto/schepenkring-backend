<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignDocument extends Model
{
    protected $fillable = [
        'sign_request_id',
        'file_path',
        'sha256',
        'type',
    ];

    public function signRequest(): BelongsTo
    {
        return $this->belongsTo(SignRequest::class);
    }
}
