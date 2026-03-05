<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerContract extends Model
{
    protected $fillable = [
        'user_id',
        'signhost_transaction_id',
        'status',
        'contract_pdf_path',
        'contract_sha256',
        'signed_document_url',
        'audit_trail_url',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
