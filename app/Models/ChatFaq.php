<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChatFaq extends Model
{
    use HasUuids;

    protected $table = 'chat_faq';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'harbor_id',
        'language',
        'question',
        'best_answer',
        'thumbs_up_count',
        'source_conversation_id',
        'created_by_admin_id',
        'indexed_at',
    ];

    protected $casts = [
        'indexed_at' => 'datetime',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
